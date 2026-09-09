<?php

use App\Enums\OrganizationRole;
use App\Enums\SoftwareBillingInterval;
use App\Models\CloudTenant;
use App\Models\Organization;
use App\Models\Software;
use App\Support\OrganizationCostBreakdownReport;

test('organization members can view the cost breakdown card on the reports index', function () {
    actingAsOrganizationMember(OrganizationRole::Member);

    $this->get(route('reports.index'))
        ->assertOk()
        ->assertSee(__('Cost Breakdown'))
        ->assertSee(__('Cost and inventory findings for'));
});

test('guests cannot view the cost breakdown report', function () {
    $this->get(route('reports.cost-breakdown'))
        ->assertRedirect(route('login'));
});

test('the cost breakdown report nests sub-products under the parent without double-counting', function () {
    [, $organization] = actingAsOrganizationMember();

    $suite = Software::factory()->recurring('monthly', 300.00)->create([
        'organization_id' => $organization->id,
        'name' => 'Atlassian',
        'vendor' => 'Atlassian',
        'currency' => 'GBP',
    ]);

    Software::factory()->recurring('monthly', 180.00)->create([
        'organization_id' => $organization->id,
        'parent_software_id' => $suite->id,
        'name' => 'Jira',
        'vendor' => 'Atlassian',
        'currency' => 'GBP',
    ]);

    Software::factory()->recurring('monthly', 120.00)->create([
        'organization_id' => $organization->id,
        'parent_software_id' => $suite->id,
        'name' => 'Confluence',
        'vendor' => 'Atlassian',
        'currency' => 'GBP',
    ]);

    Software::factory()->recurring('monthly', 50.00)->create([
        'organization_id' => $organization->id,
        'name' => 'Slack',
        'vendor' => 'Salesforce',
        'currency' => 'GBP',
    ]);

    CloudTenant::factory()->aws()->create([
        'organization_id' => $organization->id,
        'name' => 'Prod AWS',
        'billing_amount' => 90.00,
        'billing_interval' => SoftwareBillingInterval::Monthly,
        'currency' => 'GBP',
    ]);

    CloudTenant::factory()->aws()->create([
        'organization_id' => Organization::factory(),
        'name' => 'Other Org AWS',
        'billing_amount' => 999.00,
        'billing_interval' => SoftwareBillingInterval::Monthly,
        'currency' => 'GBP',
    ]);

    $report = app(OrganizationCostBreakdownReport::class)->for($organization);

    expect($report['software_total'])->toBe(350.0)
        ->and($report['cloud_total'])->toBe(90.0)
        ->and($report['estimated_monthly'])->toBe(440.0)
        ->and($report['line_item_count'])->toBe(3)
        ->and(collect($report['software'])->pluck('name')->all())->toBe(['Atlassian', 'Slack'])
        ->and(collect($report['software'])->firstWhere('name', 'Atlassian')['children'])
        ->toHaveCount(2)
        ->and(collect($report['software'])->firstWhere('name', 'Atlassian')['monthly'])->toBe(300.0)
        ->and(collect($report['cloud'])->pluck('name')->all())->toBe(['Prod AWS']);

    $this->get(route('reports.cost-breakdown'))
        ->assertOk()
        ->assertSee(__('Cost Breakdown'))
        ->assertSee('Atlassian')
        ->assertSee('Jira')
        ->assertSee('Confluence')
        ->assertSee('Slack')
        ->assertSee('Prod AWS')
        ->assertDontSee('Other Org AWS');
});

test('software section totals roll up child costs when the parent has no billing', function () {
    [, $organization] = actingAsOrganizationMember();

    $suite = Software::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Atlassian',
        'vendor' => 'Atlassian',
        'currency' => 'GBP',
        'is_recurring' => false,
        'billing_amount' => null,
        'billing_interval' => null,
    ]);

    Software::factory()->recurring('monthly', 180.00)->create([
        'organization_id' => $organization->id,
        'parent_software_id' => $suite->id,
        'name' => 'Jira',
        'vendor' => 'Atlassian',
        'currency' => 'GBP',
    ]);

    Software::factory()->recurring('monthly', 120.00)->create([
        'organization_id' => $organization->id,
        'parent_software_id' => $suite->id,
        'name' => 'Confluence',
        'vendor' => 'Atlassian',
        'currency' => 'GBP',
    ]);

    $report = app(OrganizationCostBreakdownReport::class)->for($organization);
    $parent = collect($report['software'])->firstWhere('name', 'Atlassian');

    expect($parent)->not->toBeNull()
        ->and($parent['monthly'])->toBe(300.0)
        ->and($parent['children'])->toHaveCount(2)
        ->and($report['software_total'])->toBe(300.0)
        ->and($report['line_item_count'])->toBe(1);
});

test('cost breakdown pdf downloads for organization members', function () {
    [, $organization] = actingAsOrganizationMember();

    Software::factory()->recurring('monthly', 100.00)->create([
        'organization_id' => $organization->id,
        'name' => 'Monthly App',
        'currency' => 'GBP',
    ]);

    $response = $this->get(route('reports.cost-breakdown.pdf'))
        ->assertOk();

    $contents = $response->streamedContent();

    expect($response->headers->get('content-type'))->toStartWith('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('cost-breakdown-'.$organization->slug)
        ->and($contents)->toStartWith('%PDF-1.4')
        ->and($contents)->toContain('Monthly App');
});

test('guests cannot download the cost breakdown pdf', function () {
    $this->get(route('reports.cost-breakdown.pdf'))
        ->assertRedirect(route('login'));
});
