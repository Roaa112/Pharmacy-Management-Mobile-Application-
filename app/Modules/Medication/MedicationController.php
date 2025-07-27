<?php

namespace App\Modules\Medication;

use Carbon\Carbon;
use App\Models\Medication;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\MedicationDay;
use App\Models\MedicationLog;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Modules\Medication\Services\MedicationService;
use App\Modules\Medication\Resources\MedicationResource;
use App\Modules\Medication\Requests\StoreMedicationRequest;
use App\Modules\Medication\Requests\UpdateMedicationRequest;

class MedicationController extends Controller
{
    protected $service;

    public function __construct(MedicationService $service)
    {
        $this->service = $service;
    }

public function index(Request $request)
{
    $user = $request->user();

    if (!$user) {
        Log::error('Unauthenticated user attempt');
        return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
    }

    $weekDays = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    $carbonWeekDays = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $today = Carbon::today();
    $todayIndex = array_search($today->format('l'), $carbonWeekDays);

    $result = collect();

    foreach ($weekDays as $day) {
        $targetIndex = array_search(ucfirst($day), $carbonWeekDays);
        $diff = $targetIndex - $todayIndex;
        $date = $today->copy()->addDays($diff); // التاريخ المطلوب عرضه

        $dayMedications = Medication::with('times', 'days')
            ->where('user_id', $user->id)
            ->get()
            ->filter(function ($medication) use ($day, $date, $user) {
                if ($medication->repeat_type === 'every_day') {
                    return true;
                }

                if ($medication->repeat_type === 'specific_days') {
                    return collect($medication->days)->contains(function ($d) use ($day) {
                        return Str::lower(is_object($d) ? $d->day : $d) === $day;
                    });
                }

                if ($medication->repeat_type === 'once') {
                    $medicationDay = $medication->days->first();
                    if (!$medicationDay) return false;

                    $medicationDayName = is_object($medicationDay) ? Str::lower($medicationDay->day) : Str::lower($medicationDay);
                    if ($medicationDayName !== $day) return false;

                    $now = Carbon::now();
                    $weekStart = $now->copy()->startOfWeek(Carbon::SATURDAY);
                    $weekEnd = $now->copy()->endOfWeek(Carbon::FRIDAY);

                    if ($date->lt($weekStart) || $date->gt($weekEnd)) {
                        return false;
                    }

                    return true;
                }

                return false;
            })
            ->map(function ($medication) use ($user, $date, $weekDays) {
              $medication->times = $medication->times->map(function ($time) use ($medication, $user, $date) {
    $log = MedicationLog::where('user_id', $user->id)
        ->where('medication_id', $medication->id)
        ->whereDate('shown_date', $date->toDateString())
        ->where('time', '=', $time->time) // ← بدلنا هنا فقط
        ->first();

    Log::info('Time comparison check', [
        'time_from_db' => $time->time,
        'formatted' => Carbon::parse($time->time)->format('H:i:s')
    ]);

    Log::info('Medication Time Check', [
        'user_id' => $user->id,
        'medication_id' => $medication->id,
        'date' => $date->toDateString(),
        'time' => $time->time,
        'found_status' => $log?->status,
        'log_exists' => $log ? true : false
    ]);

    $time->status = $log?->status ?? 'pending';
    return $time;
});


                if ($medication->repeat_type === 'every_day') {
                    $medication->days = collect($weekDays)->map(function ($day) {
                        return ['day' => $day];
                    });
                }

                return $medication;
            })->values();

        $result->push([
            'day' => $day,
            'medications' => MedicationResource::collection($dayMedications)
        ]);
    }

    return response()->json($result);
}



//    public function index(Request $request)
// {
//     $user = $request->user();

//     if (!$user) {
//         Log::error('Unauthenticated user attempt');
//         return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
//     }

//     $daysOfWeek = [
//         'saturday', 'sunday', 'monday',
//         'tuesday', 'wednesday', 'thursday', 'friday'
//     ];

//     $allMedications = Medication::with('times', 'days')
//         ->where('user_id', $user->id)
//         ->get();

//     $result = collect();

//     foreach ($daysOfWeek as $day) {
//         // نحسب تاريخ اليوم في الأسبوع الحاي بناءً على اسم ايوم
//         $date = Carbon::now()->startOfWeek(); // ابداية الاثنين افراضياً
//         $dayIndex = array_search($day, $daysOfWeek);
//         $date = $date->copy()->addDays($dayIndex);

//         // لو التاريخ قبل اليوم، نخلي اليوم (عشان الحاة لا تظهر كتبت بتاريخ قديمة)
//         if ($date->lt(Carbon::today())) {
//             $date = Carbon::today();
//         }

//         $filteredMeds = $allMedications->filter(function ($medication) use ($day, $date, $user) {
//             // every_day -> نظهر كل يوم
//             if ($medication->repeat_type === 'every_day') {
//                 return true;
//             }

//             // specific_days -> نظهر فقط إذا اليوم موجود في الأيام المحددة
//             if ($medication->repeat_type === 'specific_days') {
//                 return $medication->days->contains(function ($d) use ($day) {
//                     if (is_object($d)) {
//                         return Str::lower($d->day) === $day;
//                     }
//                     return Str::lower($d) === $day;
//                 });
//             }

//             // once -> نظهر فقط في اليوم المحدد ولم يؤخذ كل مواعيده
//             if ($medication->repeat_type === 'once') {
//                 $medicationDay = $medication->days->first();
//                 if (!$medicationDay) return false;

//                 $medicationDayName = is_object($medicationDay) ? Str::lower($medicationDay->day) : Str::lower($medicationDay);

//                 if ($medicationDayName !== $day) {
//                     return false;
//                 }

//                 $allTaken = $medication->times->every(function ($time) use ($medication, $user, $date) {
//                     return MedicationLog::where('user_id', $user->id)
//                         ->where('medication_id', $medication->id)
//                         ->whereDate('shown_date', $date->toDateString())
//                         ->where('time', $time->time)
//                         ->where('status', 'taken')
//                         ->exists();
//                 });

//                 return !$allTaken;
//             }

//             return false;
//         })->map(function ($medication) use ($user, $date) {

//             // عشان نرجع كل ماعيد الدواء مع حالة taken/pending بناءً على التاريخ واليوم
//             $medication->times = $medication->times->map(function ($time) use ($medication, $user, $date) {

//                 $log = MedicationLog::where('user_id', $user->id)
//                     ->where('medication_id', $medication->id)
//                     ->whereDate('shown_date', $date->toDateString())
//                     ->where('time', $time->time)
//                     ->first();

//                 $time->status = $log ? $log->status : 'pending';

//                 return $time;
//             });

//             return $medication;
//         })->values();

//         $result->push([
//             'day' => $day,
//             'medications' => MedicationResource::collection($filteredMeds)
//         ]);
//     }

//     return response()->json($result);
// }


// public function index(Request $request)
// {
//     $user = $request->user();

//     if (!$user) {
//         Log::error('Unauthenticated user attempt');
//         return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
//     }

//     $daysOfWeek = [
//         'saturday', 'sunday', 'monday',
//         'tuesday', 'wednesday', 'thursday', 'friday'
//     ];

//     // جب كل الأدوية مع اعلاقات الضرورية فعة واحدة
//     $allMedications = Medication::with('times', 'days', 'logs')
//         ->where('user_id', $user->id)
//         ->get();

//     $result = collect();

//     foreach ($daysOfWeek as $day) {
//         // حساب تاري اليوم في الأسبوع الحالي أو التالي
//         $date = Carbon::now()->startOfWeek(); // بداية الأسبوع (الإثنين)
//         $dayIndex = array_search($day, $daysOfWeek);

//         // إذا يوم السبت هو البداية حسب منطقتك، تأكد من ضب startOfWeek
//         // ملا لو أسبوعك يبدأ السبت:
//         // $date = Carbon::now()->startOfWeek(Carbon::SATURDAY);

//         // إضافة عدد الأيا للوصول لليوم المطلوب
//         $date = $date->copy()->addDays($dayIndex);

//         // إذا التاريخ أصغر من اليوم (أي في الماضي)، عد التاريخ لليوم التالي نفسه
//         if ($date->lt(Carbon::today())) {
//             $date = Carbon::today();
//         }

//         // فلترة الأدوي حسب نوع التكرار اليوم
//         $filteredMeds = $allMedications->filter(function ($medication) use ($day, $date, $user) {

//             if ($medication->repeat_type === 'every_day') {
//                 return true;
//             }

//             if ($medication->repeat_type === 'specific_days') {
//                 // نفترض علاقة days حوي كائنات أو أسماء الأيام كخاصية 'day'
//                 return $medication->days->contains(function ($d) use ($day) {
//                     if (is_object($d)) {
//                         return Str::lower($d->day) === $day;
//                     }
//                     // لو انت مصفوفة أو نص
//                     return Str::lower($d) === $day;
//                 });
//             }

//             if ($medication->repeat_type === 'once') {
//                 $medicationDay = $medication->days->first();

//                 if (!$medicationDay) return false;

//                 $medicationDayName = is_object($medicationDay) ? Str::lower($medicationDay->day) : Str::lower($medicationDay);

//                 if ($medicationDayName !== $day) {
//                     return false;
//                 }

//                 // إذا كل الأوات تم أخذها لهذا ليوم، لا نظهر الدواء
//                 $allTaken = $medication->times->every(function ($time) use ($medication, $user, $date) {
//                     return MedicationLog::where('user_id', $user->id)
//                         ->where('medication_id', $medication->id)
//                         ->whereDate('shown_date', $date->toDateString())
//                         ->where('time', $time->time)
//                         ->where('status', 'taken')
//                         ->exists();
//                 });

//                 return !$allTaken;
//             }

//             return false;
//         })->map(function ($medication) use ($user, $date) {

//             // تعديل كل الأوقات ليشمل حاة الدواء في هذا اوقت والتاريخ
//             $medication->times = $medication->times->map(function ($time) use ($medication, $user, $date) {

//                 $log = MedicationLog::where('user_id', $user->id)
//                     ->where('medication_id', $medication->id)
//                     ->whereDate('shown_date', $date->toDateString())
//                     ->where('time', $time->time)
//                     ->first();

//                 $time->status = $log ? $log->status : 'pending';

//                 return $time;
//             });

//             return $medication;
//         })->values();

//         $result->push([
//             'day' => $day,
//             'medications' => MedicationResource::collection($filteredMeds)
//         ]);
//     }

//     return response()->json($result);
// }

//     public function index(Request $request)
// {
//     $user = $request->user();

//     if (!$user) {
//         Log::error('Unauthenticated user attempt');
//         return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
//     }

//     $daysOfWeek = [
//         'saturday', 'sunday', 'monday',
//         'tuesday', 'wednesday', 'thursday', 'friday'
//     ];

//     $allMedications = Medication::with('times', 'days', 'logs')
//         ->where('user_id', $user->id)
//         ->get();

//     $result = collect();

//     foreach ($daysOfWeek as $day) {
//         $date = Carbon::parse("next $day")->startOfDay();
//         if (Carbon::now()->isSameDay($date)) {
//             $date = Carbon::today();
//         }

//         $filteredMeds = $allMedications->filter(function ($medication) use ($day, $date, $user) {
//             if ($medication->repeat_type === 'every_day') {
//                 return true;
//             }

//             if ($medication->repeat_type === 'specific_days') {
//                 return $medication->days->contains('day', $day);
//             }

//             if ($medication->repeat_type === 'once') {
//                 $medicationDay = $medication->days->first();
//                 if (!$medicationDay || Str::lower($medicationDay->day) !== $day) {
//                     return false;
//                 }

//                 $allTaken = $medication->times->every(function ($time) use ($medication, $user, $date) {
//                     return MedicationLog::where('user_id', $user->id)
//                         ->where('medication_id', $medication->id)
//                         ->whereDate('shown_date', $date)
//                         ->where('time', $time->time)
//                         ->where('status', 'taken')
//                         ->exists();
//                 });

//                 return !$allTaken;
//             }

//             return false;
//         })->map(function ($medication) use ($user, $date) {
//             $medication->times = $medication->times->map(function ($time) use ($medication, $user, $date) {
//                 $log = MedicationLog::where('user_id', $user->id)
//                     ->where('medication_id', $medication->id)
//                     ->whereDate('shown_date', $date)
//                     ->where('time', $time->time)
//                     ->first();

//                 // إضافة اخاصية بدون تحويل الكائن إلى مصفوفة
//                 $time->status = $log ? $log->status : 'pending';

//                 return $time;
//             });

//             return $medication;
//         })->values();

//         $result->push([
//             'day' => $day,
//             'medications' => MedicationResource::collection($filteredMeds)
//         ]);
//     }

//     return response()->json($result);
// }

  
    // public function index(Request $request)
    // {
    //     $user = $request->user();

    //     if (!$user) {
    //         Log::error('Unauthenticated user attempt');
    //         return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
    //     }

    //     $today = Carbon::today();
    //     $dayName = Str::lower($today->format('l'));

    //     $medications = Medication::with('times', 'days', 'logs')
    //         ->where('user_id', $user->id)
    //         ->get()
    //         ->filter(function ($medication) use ($dayName, $today, $user) {

    //             // 1. every_day
    //             if ($medication->repeat_type === 'every_day') {
    //                 return true;
    //             }

    //             // 2. specific_days
    //             if ($medication->repeat_type === 'specific_days') {
    //                 return $medication->days->contains('day', $dayName);
    //             }

    //             // 3. once
    //            if ($medication->repeat_type === 'once') {
    //     $medicationDay = $medication->days->first();
        
    //     if (!$medicationDay || Str::lower($medicationDay->day) !== $dayName) {
    //         return false; // مش اليم المسموح
    //     }

    //     $allTaken = $medication->times->every(function ($time) use ($medication, $user, $today) {
    //         return MedicationLog::where('user_id', $user->id)
    //             ->where('medication_id', $medication->id)
    //             ->whereDate('shown_date', $today)
    //             ->where('time', $time->time)
    //             ->where('status', 'taken')
    //             ->exists();
    //     });

    //     return !$allTaken;
    // }

    //             return false;
    //         })
    //         ->map(function ($medication) use ($user, $today) {
    //             // إرفاق status لل موعد
    //             $medication->times = $medication->times->map(function ($time) use ($medication, $user, $today) {
    //                 $log = MedicationLog::where('user_id', $user->id)
    //                     ->where('medication_id', $medication->id)
    //                     ->whereDate('shown_date', $today)
    //                     ->where('time', $time->time)
    //                     ->first();

    //                 return [
    //                     'time' => $time->time,
    //                     'status' => $log ? $log->status : 'pending',
    //                 ];
    //             });

    //             return $medication;
    //         })
    //         ->values();

    //     return MedicationResource::collection($medications);
    // }

   

    public function store(StoreMedicationRequest $request)
    {
        $med = $this->service->create($request->validated(), auth()->user());
     return new MedicationResource($med->load('times', 'days'));
    }

    public function update(UpdateMedicationRequest $request, Medication $medication)
    {
      
        $updated = $this->service->update($medication, $request->validated());
        return response()->json($updated->load('times', 'days'));
    }

    public function updateLogStatus(Request $request, Medication $medication)
{
    $request->validate([
        'time' => 'required|date_format:H:i:s',
        'status' => 'required|in:taken,missed',
    ]);

    $user = $request->user();

    $log = MedicationLog::firstOrCreate(
        [
            'user_id' => $user->id,
            'medication_id' => $medication->id,
            'shown_date' => Carbon::today(),
            'time' => $request->time,
        ],
        [
            'status' => $request->status,
        ]
    );

    // If it already existed, update status
    if ($log->wasRecentlyCreated === false) {
        $log->update(['status' => $request->status]);
    }

    return response()->json(['success' => true, 'message' => 'تم تحديث حالة الجرعة']);
}

    public function destroy(Medication $medication)
    {
       
        $medication->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
 public function deleteDayFromMedication(Request $request)
{
    $request->validate([
        'medication_id' => 'required|exists:medications,id',
        'day' => 'required|in:saturday,sunday,monday,tuesday,wednesday,thursday,friday',
    ]);

    $medication = Medication::find($request->medication_id);

    if (!$medication) {
        return response()->json(['success' => false, 'message' => 'Medication not found.'], 404);
    }

    // لو النوع once → نحذفه بالكامل
    if ($medication->repeat_type === 'once') {
        $medication->delete();
        return response()->json(['success' => true, 'message' => 'Medication deleted because it was one-time only.']);
    }

    // لو النع every_day → نحول لـ specific_days مع باقي الأيام
    if ($medication->repeat_type === 'every_day') {
        $allDays = ['saturday','sunday','monday','tuesday','wednesday','thursday','friday'];
        $remainingDays = array_filter($allDays, fn($d) => $d !== $request->day);

       
        MedicationDay::where('medication_id', $medication->id)->delete();

       
        foreach ($remainingDays as $day) {
            MedicationDay::create([
                'medication_id' => $medication->id,
                'day' => $day
            ]);
        }

    
        $medication->repeat_type = 'specific_days';
        $medication->save();

        return response()->json([
            'success' => true,
            'message' => 'Medication changed from every_day to specific_days, day removed.'
        ]);
    }

  
    $deleted = MedicationDay::where('medication_id', $medication->id)
        ->where('day', $request->day)
        ->delete();

    if ($deleted) {
        return response()->json(['success' => true, 'message' => 'Day removed from medication.']);
    }

    return response()->json(['success' => false, 'message' => 'Day not found for this medication.'], 404);
}



}
