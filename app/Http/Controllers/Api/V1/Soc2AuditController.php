<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Soc2\Soc2AuditReportBuilder;
use App\Support\SimplePdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Soc2AuditController extends Controller
{
    public function json(Request $request, Soc2AuditReportBuilder $builder): JsonResponse
    {
        /** @var Organization $organization */
        $organization = $request->attributes->get('organization');

        return response()->json([
            'data' => $builder->build($organization),
        ]);
    }

    public function pdf(Request $request, Soc2AuditReportBuilder $builder, SimplePdf $pdf): StreamedResponse
    {
        /** @var Organization $organization */
        $organization = $request->attributes->get('organization');
        $report = $builder->build($organization);
        $filename = 'soc2-audit-'.$organization->slug.'-'.now('UTC')->format('Ymd-His').'.pdf';
        $contents = $pdf->fromLines(
            $builder->pdfLines($report),
            'AssetBee SOC 2 Inventory Controls Report',
        );

        return response()->streamDownload(function () use ($contents): void {
            echo $contents;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
