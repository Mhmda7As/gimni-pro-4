<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Provider;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ArchiveController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->buildArchiveQuery($request);

        $appointments = $query->paginate(25)->withQueryString();
        $providers = Provider::with('specialty')->orderBy('name')->get();

        $totalAmount = (clone $this->buildArchiveQuery($request))->sum('payment_amount');

        return view('archive.index', compact('appointments', 'providers', 'totalAmount'));
    }

    public function export(Request $request)
    {
        $appointments = $this->buildArchiveQuery($request)->get();

        $fileName = 'archive_appointments_' . now()->format('Y-m-d_H-i') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $statusLabels = [
            'scheduled' => 'محجوز (منتهي)',
            'attended'  => 'حضر',
            'cancelled' => 'ملغي',
            'no_show'   => 'لم يحضر',
        ];

        $callback = function () use ($appointments, $statusLabels) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'رقم الموعد',
                'التاريخ',
                'الوقت',
                'اسم المريض',
                'رقم الهاتف',
                'الطبيب / القسم',
                'التخصص',
                'الحالة',
                'المبلغ المدفوع',
                'ملاحظات',
            ]);

            foreach ($appointments as $apt) {
                fputcsv($file, [
                    $apt->id,
                    Carbon::parse($apt->appointment_date)->format('Y-m-d'),
                    Carbon::parse($apt->start_time)->format('H:i'),
                    $this->sanitizeCsvCell($apt->patient->last_registered_name ?? 'غير محدد'),
                    $this->sanitizeCsvCell($apt->patient->phone_number ?? ''),
                    $this->sanitizeCsvCell($apt->provider->name ?? 'غير محدد'),
                    $this->sanitizeCsvCell($apt->provider->specialty->name ?? ''),
                    $statusLabels[$apt->status] ?? $apt->status,
                    number_format((float) $apt->payment_amount, 2, '.', ''),
                    $this->sanitizeCsvCell($apt->notes ?? ''),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function buildArchiveQuery(Request $request)
    {
        $today = Carbon::today();

        $query = Appointment::with(['patient', 'provider.specialty'])
            ->where(function ($q) use ($today) {
                $q->whereDate('appointment_date', '<', $today)
                  ->orWhere('is_archived', true);
            });

        if ($search = trim((string) $request->input('search'))) {
            $query->whereHas('patient', function ($q) use ($search) {
                $q->where('last_registered_name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        if ($providerId = $request->input('provider_id')) {
            $query->where('provider_id', $providerId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('appointment_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('appointment_date', '<=', $dateTo);
        }

        return $query->orderByDesc('appointment_date')->orderByDesc('start_time');
    }

    private function sanitizeCsvCell(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }
        return $value;
    }
}