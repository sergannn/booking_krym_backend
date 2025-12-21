<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Excursion;
use App\Models\ExcursionPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ExcursionPriceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Тест создания экскурсии с ценами через форму MoonShine
     * Симулирует отправку формы точно так же, как в ExcursionResource
     */
    public function test_excursion_creation_with_prices_via_form(): void
    {
        $futureDate = Carbon::now()->addDays(7)->format('Y-m-d H:i:s');
        
        // Создаем request с данными формы (как в MoonShine)
        $formData = [
            'title' => 'Тестовая экскурсия',
            'description' => 'Описание тестовой экскурсии',
            'date_time' => $futureDate,
            'price' => null,
            'max_seats' => 50,
            'is_active' => true,
            // Поля цен из формы (как в ExcursionResource)
            'price_adult_without_entry' => 1000.00,
            'price_adult_with_entry' => 1500.00,
            'price_adult_seller_commission' => 10,
            'price_adult_partner_commission' => 5,
            'price_child_without_entry' => 500.00,
            'price_child_with_entry' => 750.00,
            'price_child_seller_commission' => 10,
            'price_child_partner_commission' => 5,
            'price_senior_without_entry' => 800.00,
            'price_senior_with_entry' => 1200.00,
            'price_senior_seller_commission' => 10,
            'price_senior_partner_commission' => 5,
            'price_disabled_without_entry' => 600.00,
            'price_disabled_with_entry' => 900.00,
            'price_disabled_seller_commission' => 10,
            'price_disabled_partner_commission' => 5,
        ];

        // Создаем request
        $request = Request::create('/admin/resource/excursion-resource', 'POST', $formData);
        app()->instance('request', $request);

        // Создаем экскурсию через модель (симулируем сохранение через MoonShine)
        $excursion = new Excursion();
        $excursion->fill([
            'title' => $formData['title'],
            'description' => $formData['description'],
            'date_time' => $formData['date_time'],
            'price' => $formData['price'],
            'max_seats' => $formData['max_seats'],
            'is_active' => $formData['is_active'],
        ]);
        $excursion->save();

        // Симулируем работу ExcursionResource::onBeforeSave и onAfterSave
        // onBeforeSave - сохраняем данные цен (точно как в ExcursionResource)
        $savedPriceData = [];
        $types = ['adult', 'child', 'senior', 'disabled'];
        
        foreach ($types as $type) {
            $savedPriceData[$type] = [
                'price_without_entry' => $request->input("price_{$type}_without_entry"),
                'price_with_entry' => $request->input("price_{$type}_with_entry"),
                'seller_commission_percent' => $request->input("price_{$type}_seller_commission", 10),
                'partner_commission_percent' => $request->input("price_{$type}_partner_commission", 10),
            ];
        }

        // onAfterSave - сохраняем цены в базу (точно как в ExcursionResource)
        if (!empty($savedPriceData)) {
            $hasAnyPriceData = false;
            
            foreach ($savedPriceData as $type => $priceData) {
                // Удаляем null значения, но оставляем 0
                $filteredData = array_filter($priceData, fn($value) => $value !== null);
                
                if (!empty($filteredData)) {
                    $hasAnyPriceData = true;
                    $excursion->prices()->updateOrCreate(
                        ['passenger_type' => $type],
                        $filteredData
                    );
                }
            }
        }

        // Проверяем, что экскурсия создана
        $this->assertDatabaseHas('excursions', [
            'id' => $excursion->id,
            'title' => 'Тестовая экскурсия',
        ]);

        // Проверяем, что цены сохранены
        $this->assertDatabaseHas('excursion_prices', [
            'excursion_id' => $excursion->id,
            'passenger_type' => 'adult',
            'price_without_entry' => 1000.00,
            'price_with_entry' => 1500.00,
            'seller_commission_percent' => 10,
            'partner_commission_percent' => 5,
        ]);

        $this->assertDatabaseHas('excursion_prices', [
            'excursion_id' => $excursion->id,
            'passenger_type' => 'child',
            'price_without_entry' => 500.00,
            'price_with_entry' => 750.00,
            'seller_commission_percent' => 10,
            'partner_commission_percent' => 5,
        ]);

        // Проверяем accessors
        $excursion->load('prices');
        $this->assertEquals(1000.00, $excursion->price_adult_without_entry);
        $this->assertEquals(1500.00, $excursion->price_adult_with_entry);
        $this->assertEquals(500.00, $excursion->price_child_without_entry);
        $this->assertEquals(750.00, $excursion->price_child_with_entry);
    }

    /**
     * Тест создания экскурсии без цен (должны создаться дефолтные)
     */
    public function test_excursion_creation_with_default_prices(): void
    {
        $futureDate = Carbon::now()->addDays(7)->format('Y-m-d H:i:s');
        
        // Создаем экскурсию с базовой ценой
        $excursion = Excursion::create([
            'title' => 'Экскурсия с дефолтными ценами',
            'description' => 'Описание',
            'date_time' => $futureDate,
            'price' => 2000.00,
            'max_seats' => 50,
            'is_active' => true,
        ]);

        // Создаем дефолтные цены
        $excursion->createDefaultPrices();

        // Проверяем, что дефолтные цены созданы для всех типов
        $types = ['adult', 'child', 'senior', 'disabled'];
        foreach ($types as $type) {
            $this->assertDatabaseHas('excursion_prices', [
                'excursion_id' => $excursion->id,
                'passenger_type' => $type,
                'price_without_entry' => 2000.00,
                'price_with_entry' => 2000.00,
                'seller_commission_percent' => 10,
                'partner_commission_percent' => 10,
            ]);
        }
    }

    /**
     * Тест обновления цен существующей экскурсии через форму MoonShine
     * Симулирует отправку формы точно так же, как в ExcursionResource
     */
    public function test_excursion_price_update_via_form(): void
    {
        $futureDate = Carbon::now()->addDays(7)->format('Y-m-d H:i:s');
        
        // Создаем экскурсию
        $excursion = Excursion::create([
            'title' => 'Экскурсия для обновления',
            'description' => 'Описание',
            'date_time' => $futureDate,
            'price' => 1000.00,
            'max_seats' => 50,
            'is_active' => true,
        ]);

        // Создаем начальные цены
        $excursion->createDefaultPrices();

        // Создаем request с обновленными данными формы (как в MoonShine)
        $formData = [
            'title' => 'Экскурсия для обновления',
            'description' => 'Описание',
            'date_time' => $futureDate,
            'price' => 1000.00,
            'max_seats' => 50,
            'is_active' => true,
            // Обновленные поля цен из формы (как в ExcursionResource)
            'price_adult_without_entry' => 2000.00,
            'price_adult_with_entry' => 2500.00,
            'price_adult_seller_commission' => 15,
            'price_adult_partner_commission' => 8,
            'price_child_without_entry' => 500.00,
            'price_child_with_entry' => 750.00,
            'price_child_seller_commission' => 10,
            'price_child_partner_commission' => 5,
        ];

        // Создаем request
        $request = Request::create('/admin/resource/excursion-resource/' . $excursion->id, 'PUT', $formData);
        app()->instance('request', $request);

        // Симулируем работу ExcursionResource::onBeforeSave и onAfterSave
        // onBeforeSave - сохраняем данные цен (точно как в ExcursionResource)
        $savedPriceData = [];
        $types = ['adult', 'child', 'senior', 'disabled'];
        
        foreach ($types as $type) {
            $savedPriceData[$type] = [
                'price_without_entry' => $request->input("price_{$type}_without_entry"),
                'price_with_entry' => $request->input("price_{$type}_with_entry"),
                'seller_commission_percent' => $request->input("price_{$type}_seller_commission", 10),
                'partner_commission_percent' => $request->input("price_{$type}_partner_commission", 10),
            ];
        }

        // onAfterSave - сохраняем цены в базу (точно как в ExcursionResource)
        if (!empty($savedPriceData)) {
            $hasAnyPriceData = false;
            
            foreach ($savedPriceData as $type => $priceData) {
                // Удаляем null значения, но оставляем 0
                $filteredData = array_filter($priceData, fn($value) => $value !== null);
                
                if (!empty($filteredData)) {
                    $hasAnyPriceData = true;
                    $excursion->prices()->updateOrCreate(
                        ['passenger_type' => $type],
                        $filteredData
                    );
                }
            }
        }

        // Проверяем обновление
        $this->assertDatabaseHas('excursion_prices', [
            'excursion_id' => $excursion->id,
            'passenger_type' => 'adult',
            'price_without_entry' => 2000.00,
            'price_with_entry' => 2500.00,
            'seller_commission_percent' => 15,
            'partner_commission_percent' => 8,
        ]);

        // Проверяем accessors после обновления
        $excursion->load('prices');
        $this->assertEquals(2000.00, $excursion->price_adult_without_entry);
        $this->assertEquals(2500.00, $excursion->price_adult_with_entry);
        $this->assertEquals(15, $excursion->price_adult_seller_commission);
        $this->assertEquals(8, $excursion->price_adult_partner_commission);
    }

    /**
     * Тест создания экскурсии без основной цены (price = null)
     */
    public function test_excursion_creation_with_null_price(): void
    {
        $futureDate = Carbon::now()->addDays(7)->format('Y-m-d H:i:s');
        
        // Создаем экскурсию без основной цены
        $excursion = Excursion::create([
            'title' => 'Экскурсия без основной цены',
            'description' => 'Описание',
            'date_time' => $futureDate,
            'price' => null,
            'max_seats' => 50,
            'is_active' => true,
        ]);

        // Проверяем, что экскурсия создана с null ценой
        $this->assertDatabaseHas('excursions', [
            'id' => $excursion->id,
            'price' => null,
        ]);

        // Удаляем автоматически созданные цены (из события created)
        $excursion->prices()->delete();

        // Создаем цены вручную
        $excursion->prices()->create([
            'passenger_type' => 'adult',
            'price_without_entry' => 1000.00,
            'price_with_entry' => 1500.00,
            'seller_commission_percent' => 10,
            'partner_commission_percent' => 5,
        ]);

        // Проверяем, что цены сохранены
        $this->assertDatabaseHas('excursion_prices', [
            'excursion_id' => $excursion->id,
            'passenger_type' => 'adult',
            'price_without_entry' => 1000.00,
            'price_with_entry' => 1500.00,
        ]);
    }

    /**
     * Тест проверки всех accessors для всех типов пассажиров
     */
    public function test_all_price_accessors(): void
    {
        $futureDate = Carbon::now()->addDays(7)->format('Y-m-d H:i:s');
        
        $excursion = Excursion::create([
            'title' => 'Тест accessors',
            'description' => 'Описание',
            'date_time' => $futureDate,
            'price' => null,
            'max_seats' => 50,
            'is_active' => true,
        ]);

        // Удаляем автоматически созданные цены (из события created)
        $excursion->prices()->delete();

        // Создаем цены для всех типов
        $prices = [
            'adult' => [1000, 1500, 10, 5],
            'child' => [500, 750, 10, 5],
            'senior' => [800, 1200, 10, 5],
            'disabled' => [600, 900, 10, 5],
        ];

        foreach ($prices as $type => [$without, $with, $seller, $partner]) {
            $excursion->prices()->create([
                'passenger_type' => $type,
                'price_without_entry' => $without,
                'price_with_entry' => $with,
                'seller_commission_percent' => $seller,
                'partner_commission_percent' => $partner,
            ]);
        }

        $excursion->load('prices');

        // Проверяем все accessors
        $this->assertEquals(1000, $excursion->price_adult_without_entry);
        $this->assertEquals(1500, $excursion->price_adult_with_entry);
        $this->assertEquals(10, $excursion->price_adult_seller_commission);
        $this->assertEquals(5, $excursion->price_adult_partner_commission);

        $this->assertEquals(500, $excursion->price_child_without_entry);
        $this->assertEquals(750, $excursion->price_child_with_entry);
        $this->assertEquals(10, $excursion->price_child_seller_commission);
        $this->assertEquals(5, $excursion->price_child_partner_commission);

        $this->assertEquals(800, $excursion->price_senior_without_entry);
        $this->assertEquals(1200, $excursion->price_senior_with_entry);
        $this->assertEquals(10, $excursion->price_senior_seller_commission);
        $this->assertEquals(5, $excursion->price_senior_partner_commission);

        $this->assertEquals(600, $excursion->price_disabled_without_entry);
        $this->assertEquals(900, $excursion->price_disabled_with_entry);
        $this->assertEquals(10, $excursion->price_disabled_seller_commission);
        $this->assertEquals(5, $excursion->price_disabled_partner_commission);
    }
}

