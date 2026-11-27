<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function expenses(Request $request)
    {
        $this->authorize('viewAny', Expense::class);

        $format = $request->query('format', 'csv');
        if (! in_array($format, ['csv', 'pdf'])) {
            return response()->json(['status' => 'error', 'message' => 'Invalid format. Use csv or pdf.'], 400);
        }

        $companyId = auth()->user()->company_id;

        $expenses = Expense::where('company_id', $companyId)
            ->with(['user', 'attachments'])
            ->latest()
            ->get();

        if ($format === 'csv') {
            return $this->exportCsv($expenses, 'expenses');
        }

        return $this->exportPdf($expenses, 'expenses');
    }

    private function exportCsv($data, $fileName)
    {
        $csvData = [];

        // Add headers
        $csvData[] = ['ID', 'Title', 'Amount', 'Category', 'User', 'Date', 'Created At'];

        // Add data rows
        foreach ($data as $item) {
            $csvData[] = [
                $item->id,
                $item->title,
                $item->amount,
                $item->category,
                $item->user->name ?? 'N/A',
                $item->expense_date ?? 'N/A',
                $item->created_at->format('Y-m-d H:i:s'),
            ];
        }

        $csv = '';
        foreach ($csvData as $row) {
            $csv .= implode(',', $row)."\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'_'.now()->format('Y-m-d_H-i-s').'.csv"',
        ]);
    }

    private function exportPdf($data, $fileName)
    {
        $html = $this->generateHtmlTable($data);

        $pdf = Pdf::loadHTML($html);

        return $pdf->download($fileName.'_'.now()->format('Y-m-d_H-i-s').'.pdf');
    }

    private function generateHtmlTable($expenses)
    {
        $html = '
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; font-weight: bold; }
                tr:nth-child(even) { background-color: #f9f9f9; }
            </style>
        </head>
        <body>
            <h2>Expense Report</h2>
            <p>Generated on: '.now()->format('Y-m-d H:i:s').'</p>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Amount</th>
                        <th>Category</th>
                        <th>User</th>
                        <th>Date</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($expenses as $expense) {
            $html .= '
                    <tr>
                        <td>'.$expense->id.'</td>
                        <td>'.$expense->title.'</td>
                        <td>'.$expense->amount.'</td>
                        <td>'.$expense->category.'</td>
                        <td>'.($expense->user->name ?? 'N/A').'</td>
                        <td>'.($expense->expense_date ?? 'N/A').'</td>
                        <td>'.$expense->created_at->format('Y-m-d H:i:s').'</td>
                    </tr>';
        }

        $html .= '
                </tbody>
            </table>
        </body>
        </html>';

        return $html;
    }
}
