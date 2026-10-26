<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;
use Unico\Core\Models\AddressType;

class ClientAddressesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_new_person_gets_residence_and_domicile_addresses(): void
    {
        AddressType::query()->create(['name' => 'Residenza']);
        AddressType::query()->create(['name' => 'Domicilio']);

        $person = Client::query()->create(['name' => 'Gioia Quarto', 'is_person' => true]);

        $this->assertSame(2, $person->addresses()->count());
    }
}
