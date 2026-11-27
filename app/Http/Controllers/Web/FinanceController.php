<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Provider;
use App\Models\Specialty;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        $query = $this->buildFinanceQuery($request, $month, $year);

        $totalRevenue = (clone $query)->sum('payment_amount');
        $paidVisitsCount = (clone $query)->where('payment_amount', '>', 0)->count();
        $totalVisitsCount = (clone $query)->count();

        $appointments = $query->paginate(25)->withQueryString();

        // تعمية بيانات المرضى في السيرفر قبل إرسالها للواجهة
        $appointments->getCollection()->transform(function ($apt) {
            $apt->masked_patient_name = $this->maskName($apt->patient->last_registered_name ?? '');
            $apt->masked_patient_phone = $this->maskPhone($apt->patient->phone_number ?? '');
            return $apt;
        });

        $specialties = Specialty::orderBy('name')->get();
        $providers = Provider::orderBy('name')->get();

        return view('finance.index', compact(
            'appointments',
            'specialties',
            'providers',
            'totalRevenue',
            'paidVisitsCount',
            'totalVisitsCount',
            'month',
            'year'
        ));
    }

    /**
     * تصدير التقرير المالي إلى Excel (مع الحفاظ على تعمية بيانات المرضى)
     */
    public function export(Request $request)
    {
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        $appointments = $this->buildFinanceQuery($request, $month, $year)->get();

        $fileName = "finance_report_{$year}_{$month}.csv";

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($appointments) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'رقم الموعد',
                'التاريخ',
                'المريض (معمّى)',
                'الهاتف (معمّى)',
                'التخصص',
                'الطبيب / القسم',
                'حالة الزيارة',
                'المبلغ المدفوع',
            ]);

            foreach ($appointments as $apt) {
                fputcsv($file, [
                    $apt->id,
                    Carbon::parse($apt->appointment_date)->format('Y-m-d'),
                    $this->sanitizeCsvCell($this->maskName($apt->patient->last_registered_name ?? '')),
                    $this->sanitizeCsvCell($this->maskPhone($apt->patient->phone_number ?? '')),
                    $this->sanitizeCsvCell($apt->provider->specialty->name ?? ''),
                    $this->sanitizeCsvCell($apt->provider->name ?? ''),
                    $apt->status === 'attended' ? 'حضر' : $apt->status,
                    number_format((float) $apt->payment_amount, 2, '.', ''),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function buildFinanceQuery(Request $request, $month, $year)
    {
        $query = Appointment::with(['patient', 'provider.specialty'])
            ->where('status', '!=', 'cancelled');

        if ($year && $year !== 'all') {
            $query->whereYear('appointment_date', (int) $year);
        }

        if ($month && $month !== 'all') {
            $query->whereMonth('appointment_date', (int) $month);
        }

        if ($specialtyId = $request->input('specialty_id')) {
            $query->whereHas('provider', fn($q) => $q->where('specialty_id', $specialtyId));
        }

        if ($providerId = $request->input('provider_id')) {
            $query->where('provider_id', $providerId);
        }

        return $query->orderByDesc('appointment_date')->orderByDesc('start_time');
    }

    /**
     * تعمية الاسم في السيرفر: إظهار أول حرفين فقط
     */
    private function maskName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '***';
        }
        return mb_substr($name, 0, 2, 'UTF-8') . '***';
    }

    /**
     * تعمية الهاتف في السيرفر: إظهار آخر 4 أرقام فقط
     */
    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) < 4) {
            return '******';
        }
        return '******' . substr($digits, -4);
    }

    private function sanitizeCsvCell(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }
        return $value;
    }
}