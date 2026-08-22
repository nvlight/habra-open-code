<?php

use App\Models\Company;
use App\Models\Industry;
use App\Models\Publication;
use App\Models\User;

it('lists companies sorted by rating', function () {
    $weak = Company::factory()->create();
    $weak->forceFill(['rating' => 12])->save();
    $top = Company::factory()->create();
    $top->forceFill(['rating' => 1400])->save();

    $data = $this->getJson('/api/companies')->assertOk()->json('data');

    expect($data)->toHaveCount(2)
        ->and($data[0]['slug'])->toBe($top->slug);
});

it('shows a company card with industries and representative', function () {
    $industry = Industry::factory()->create();
    $company = Company::factory()->create(['slug' => 'acme']);
    $representative = User::factory()->create(['login' => 'acme_team']);

    $company->update(['representative_id' => $representative->id]);
    $company->industries()->sync([$industry->id]);
    Publication::factory()->published()->count(3)->create(['company_id' => $company->id]);

    $this->getJson('/api/companies/acme')
        ->assertOk()
        ->assertJsonPath('data.slug', 'acme')
        ->assertJsonPath('data.representative.login', 'acme_team')
        ->assertJsonPath('data.industries.0.id', $industry->id)
        ->assertJsonPath('data.publications_count', 3);
});

it('lists company employees', function () {
    $company = Company::factory()->create();
    $employee = User::factory()->create();

    $employee->update(['company_id' => $company->id]);
    $outsider = User::factory()->create();

    $data = $this->getJson("/api/companies/{$company->slug}/employees")->assertOk()->json('data');

    expect(collect($data)->pluck('id')->all())->toEqual([$employee->id])
        ->and(collect($data)->pluck('id'))->not->toContain($outsider->id);
});
