<?php

namespace App\Http\Controllers\Reports;

use App\Enums\InventoryReport;
use App\Support\CurrentOrganization;
use App\Support\OrganizationInventoryReports;
use App\Support\SimplePdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadInventoryReportController
{
    use AuthorizesRequests;

    public function __invoke(
        string $report,
        OrganizationInventoryReports $reports,
        SimplePdf $pdf,
    ): StreamedResponse {
        $organization = CurrentOrganization::require();
        $this->authorize('viewReports', $organization);

        $reportType = InventoryReport::tryFrom($report) ?? abort(404);
        $filename = $reportType->value.'-'.$organization->slug.'-'.now('UTC')->format('Ymd-His').'.pdf';
        $contents = $pdf->fromLines(
            $reports->pdfLines($organization, $reportType),
            $reportType->title(),
        );

        return response()->streamDownload(function () use ($contents): void {
            echo $contents;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
