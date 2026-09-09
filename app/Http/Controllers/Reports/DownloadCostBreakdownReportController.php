<?php

namespace App\Http\Controllers\Reports;

use App\Support\CurrentOrganization;
use App\Support\OrganizationCostBreakdownReport;
use App\Support\SimplePdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadCostBreakdownReportController
{
    use AuthorizesRequests;

    public function __invoke(
        OrganizationCostBreakdownReport $reports,
        SimplePdf $pdf,
    ): StreamedResponse {
        $organization = CurrentOrganization::require();
        $this->authorize('viewReports', $organization);

        $filename = 'cost-breakdown-'.$organization->slug.'-'.now('UTC')->format('Ymd-His').'.pdf';
        $contents = $pdf->fromLines(
            $reports->pdfLines($organization),
            __('Cost Breakdown'),
        );

        return response()->streamDownload(function () use ($contents): void {
            echo $contents;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
