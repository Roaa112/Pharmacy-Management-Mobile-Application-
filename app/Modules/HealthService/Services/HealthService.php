<?php


namespace App\Modules\HealthService\Services;

use Carbon\Carbon;
use App\Models\OvulationResult;
use App\Models\BodyWeightResult;
use App\Models\VaccinationSchedule;
use App\Models\PregnancyCalculation;
use App\Models\BloodSugarMeasurement;
use App\Models\BloodPressureMeasurement;


class HealthService
{


    public function getAllOvulationResults()
    {
        return OvulationResult::where('user_id', auth()->id())->latest()->get();
    }

    public function getAllBodyWeightResults()
    {
        return BodyWeightResult::where('user_id', auth()->id())->latest()->get();
    }

    public function getAllBloodSugarResults()
    {
        return BloodSugarMeasurement::where('user_id', auth()->id())->latest()->get();
    }

    public function getAllBloodPressureResults()
    {
        return BloodPressureMeasurement::where('user_id', auth()->id())->latest()->get();
    }

    public function getAllPregnancyCalculations()
    {
        return PregnancyCalculation::where('user_id', auth()->id())->latest()->get();
    }

    public function getAllVaccinationSchedules()
    {
        return VaccinationSchedule::where('user_id', auth()->id())->latest()->get();
    }
public function calculateOvulation($userId, array $validated)
{
    $startDate = Carbon::parse($validated['start_day_of_cycle']);
    $cycleLength = $validated['cycle_length'];
    $periodLength = $validated['period_length'];

    $ovulationDay = $startDate->copy()->addDays($cycleLength - 14);
    $fertileStart = $ovulationDay->copy()->subDays(5);
    $fertileEnd = $ovulationDay->copy()->addDays(2);

    $phases = [];
    $days = [];

    for ($i = 0; $i < $cycleLength; $i++) {
        $day = $startDate->copy()->addDays($i);
        $phase = 'غير خصبة';
        $chance = '0%';

        if ($i < $periodLength) {
            $phase = 'حيض';
        } elseif ($day->between($fertileStart, $fertileEnd)) {
            $daysFromOvulation = $day->diffInDays($ovulationDay, false);

            if ($day->equalTo($ovulationDay)) {
                $phase = 'تبويض';
                $chance = '30%';
            } elseif (in_array($daysFromOvulation, [-1, -2])) {
                $phase = 'خصوبة عالية';
                $chance = '25%';
            } elseif (in_array($daysFromOvulation, [-3, -4])) {
                $phase = 'خصوبة متوسطة';
                $chance = '15%';
            } elseif ($daysFromOvulation == -5) {
                $phase = 'خصوبة منخفضة';
                $chance = '10%';
            } elseif (in_array($daysFromOvulation, [1, 2])) {
                $phase = 'نهاية التبويض';
                $chance = '5%';
            }
        } elseif ($day->greaterThan($fertileEnd)) {
            $phase = 'ما بعد التبويض';
        }

        $formattedDate = $day->format('Y-m-d');

        $phases[$formattedDate] = [
            'phase' => $phase,
            'pregnancy_chance' => $chance,
        ];

        $days[] = [
            'date' => $formattedDate,
            'phase' => $phase,
            'pregnancy_chance' => $chance,
        ];
    }

    $result = OvulationResult::create([
        'user_id' => $userId,
        'start_day_of_cycle' => $startDate,
        'cycle_length' => $cycleLength,
        'period_length' => $periodLength,
        'ovulation_date' => $ovulationDay,
        'fertile_start' => $fertileStart,
        'fertile_end' => $fertileEnd,
        'cycle_phase_by_day' => $phases,
    ]);

    return [
        'message' => 'تم حساب التبويض بنجاح',
        'data' => [
            'period' => [
                'user_id' => $userId,
                'start_day_of_cycle' => $startDate->toISOString(),
                'cycle_length' => $cycleLength,
                'period_length' => $periodLength,
            ],
            'ovulation_date' => $ovulationDay->toDateString(),
            'fertile_start' => $fertileStart->toDateString(),
            'fertile_end' => $fertileEnd->toDateString(),
            'days' => $days,
        ],
    ];
}



   public function calculateBodyWeight($userId, array $data)
{
    $heightMeters = $data['unit'] === 'metric' ? $data['height'] / 100 : $data['height'] * 0.0254;
    $weightKg = $data['unit'] === 'metric' ? $data['weight'] : $data['weight'] * 0.453592;
    $bmi = round($weightKg / ($heightMeters * $heightMeters), 1); // زي الصورة: رقم عشري واحد

    // وصف الحالة حسب قيمة BMI
    $status = '';
    $description = '';

    if ($bmi < 18.5) {
        $status = 'نقص في الوزن';
        $description = 'مؤشر كتلة الجسم الخاص بك أقل من 18.5، وهذا يعني أن وزنك أقل من الطبيعي. يُفضل مراجعة طبيب.';
    } elseif ($bmi < 25) {
        $status = 'وزن طبيعي';
        $description = 'وزنك ضمن النطاق الصحي. استمر في الحفاظ على أسلوب حياة متوازن.';
    } elseif ($bmi < 30) {
        $status = 'زيادة في الوزن';
        $description = 'مؤشر كتلة الجسم بين 25 و30 يشير إلى زيادة في الوزن، مما قد يؤدي لمشاكل صحية لاحقًا.';
    } elseif ($bmi < 35) {
        $status = 'سمنة من الدرجة الأولى';
        $description = 'وزنك يُصنف كسمنة من الدرجة الأولى. يُنصح باتباع نظام غذائي صحي.';
    } elseif ($bmi < 40) {
        $status = 'سمنة من الدرجة الثانية';
        $description = 'سمنة متوسطة، يُفضل مراجعة مختص تغذية واتباع خطة صحية.';
    } else {
        $status = 'سمنة من الدرجة الثالثة';
        $description = 'سمنة مفرطة. يُنصح بمراجعة الطبيب لاتخاذ خطوات لتحسين صحتك.';
    }

    // حساب نطاق الوزن المثالي (BMI بين 18.5 و 24.9)
    $minIdealWeight = round(18.5 * ($heightMeters ** 2), 1);
    $maxIdealWeight = round(24.9 * ($heightMeters ** 2), 1);
    $idealRange = "{$minIdealWeight} - {$maxIdealWeight} كجم";

    // حفظ البيانات في قاعدة البيانات (اختياري)
    $result = BodyWeightResult::create([
        'user_id' => $userId,
        'height' => $data['height'],
        'weight' => $data['weight'],
        'unit' => $data['unit'],
        'bmi_result' => $bmi,
        'bmi_status' => $status,
        'bmi_description' => $description,
        'ideal_range' => $idealRange,
    ]);

    return [
        'message' => 'تم حساب مؤشر كتلة الجسم بنجاح',
        'data' => [
            'bmi' => $bmi,
            'status' => $status,
            'description' => $description,
            'ideal_range' => $idealRange,
        ],
    ];
}

  public function storeBloodSugar($userId, array $data)
{
    $data['user_id'] = $userId;
    $record = BloodSugarMeasurement::create($data);

    $evaluation = $this->evaluateBloodSugar($data['value'], $data['condition_type']);

    return [
        'message' => 'تم تسجيل قياس سكر الدم بنجاح',
        'data' => [
            'value' => $record->value,
            'condition_type' => $record->condition_type,
            'measured_at' => $record->measured_at,
            'evaluation' => $evaluation
        ]
    ];
}

private function evaluateBloodSugar($value, $condition)
{
    // ترجمة القيم القادمة من الواجهة الإنجليزية إلى العربية
    $condition = match($condition) {
        'fasting' => 'صائم',
        'before_breakfast' => 'قبل الإفطار',
        'after_meal' => 'بعد الأكل',
        'random' => 'عشوائي',
        default => $condition
    };

    switch ($condition) {
        case 'قبل الإفطار':
        case 'صائم':
            return $value < 70 ? 'منخفض' : ($value <= 99 ? 'طبيعي' : 'مرتفع');
        case 'بعد الأكل':
            return $value < 140 ? 'طبيعي' : 'مرتفع';
        case 'عشوائي':
            return $value < 200 ? 'طبيعي' : 'مرتفع';
        default:
            return 'غير معروف';
    }
}



    // public function storeBloodPressure($userId, array $data)
    // {
    //     $data['user_id'] = $userId;
    //     $record = BloodPressureMeasurement::create($data);

    //     $evaluation = $this->evaluateBloodPressure($data['systolic'], $data['diastolic']);

    //     return [
    //         'message' => 'تم تسجيل قياس ضغط الدم بنجاح',
    //         'data' => [
    //             'systolic' => $record->systolic,
    //             'diastolic' => $record->diastolic,
    //             'condition_type' => $record->condition_type,
    //             'measured_at' => $record->measured_at,
    //             'evaluation' => $evaluation
    //         ]
    //     ];
    // }

    // private function evaluateBloodPressure($systolic, $diastolic)
    // {
    //     if ($systolic < 90 && $diastolic < 60) {
    //         return 'ضغط منخفض';
    //     } elseif ($systolic >= 140 || $diastolic >= 90) {
    //         return 'ضغط مرتفع (المرحلة الثانية)';
    //     } elseif ($systolic >= 130 || $diastolic >= 80) {
    //         return 'ضغط مرتفع (المرحلة الأولى)';
    //     } elseif ($systolic < 120 && $diastolic < 80) {
    //         return 'طبيعي';
    //     } else {
    //         return 'غير منتظم - يحتاج تقييم إضافي';
    //     }
    // }


    public function storeBloodPressure($userId, array $data)
{
    $data['user_id'] = $userId;
    $record = BloodPressureMeasurement::create($data);

    // تعديل: مرر نوع الحالة إلى التقييم
    $evaluation = $this->evaluateBloodPressure(
        $data['systolic'],
        $data['diastolic'],
        $data['condition_type'] ?? null
    );

    return [
        'message' => 'تم تسجيل قياس ضغط الدم بنجاح',
        'data' => [
            'systolic' => $record->systolic,
            'diastolic' => $record->diastolic,
            'condition_type' => $record->condition_type,
            'measured_at' => $record->measured_at,
            'evaluation' => $evaluation
        ]
    ];
}

private function evaluateBloodPressure($systolic, $diastolic, $conditionType = null)
{
    // السماح بهامش بسيط بعد الأكل
    $systolicAdjustment = ($conditionType === 'بعد الأكل') ? -5 : 0;
    $diastolicAdjustment = ($conditionType === 'بعد الأكل') ? -5 : 0;

    $adjustedSystolic = $systolic + $systolicAdjustment;
    $adjustedDiastolic = $diastolic + $diastolicAdjustment;

    if ($adjustedSystolic < 90 && $adjustedDiastolic < 60) {
        return 'ضغط منخفض';
    } elseif ($adjustedSystolic >= 140 || $adjustedDiastolic >= 90) {
        return 'ضغط مرتفع (المرحلة الثانية)';
    } elseif ($adjustedSystolic >= 130 || $adjustedDiastolic >= 80) {
        return 'ضغط مرتفع (المرحلة الأولى)';
    } elseif ($adjustedSystolic < 120 && $adjustedDiastolic < 80) {
        return 'طبيعي';
    } else {
        return 'غير منتظم - يحتاج تقييم إضافي';
    }
}

   public function calculatePregnancy($userId, array $data)
   {
    $lastPeriod = Carbon::parse($data['last_period_date']);
    $pregnancyStart = $lastPeriod; // الحمل بيبدأ فعلياً بعد أسبوع من أول يوم آخر دورة
    $now = Carbon::now();

    // تاريخ الولادة المتوقع (280 يوم من أول يوم آخر دورة)
    $dueDate = $lastPeriod->copy()->addDays(280);

    // الفرق من بداية الحمل الحقيقي
    $diffInDays = $pregnancyStart->diffInDays($now);
    $currentWeek = max(1, intdiv($diffInDays, 7));
    $currentDay = $diffInDays % 7;

    $monthNumber = ceil($currentWeek / 4);
    $weekInMonth = $currentWeek % 4 === 0 ? 4 : $currentWeek % 4;
    $dayInWeek = $currentDay ; // لأنه يبدأ من 0

    $monthInfo = "اليوم {$dayInWeek} من الأسبوع {$weekInMonth} في الشهر {$monthNumber}";


    // حجم الجنين
    $babySizes = [
        5 => ['size' => 'بحجم بذرة سمسم', 'weight' => '0.1 جم', 'length' => '0.1 سم'],
        10 => ['size' => 'بحجم برقوق', 'weight' => '5 جم', 'length' => '3.1 سم'],
        20 => ['size' => 'بحجم موزة', 'weight' => '300 جم', 'length' => '25 سم'],
        30 => ['size' => 'بحجم كوسة', 'weight' => '1.5 كجم', 'length' => '38 سم'],
        40 => ['size' => 'بحجم بطيخة', 'weight' => '3.5 كجم', 'length' => '51 سم'],
    ];

    $babyInfo = ['size' => 'غير متاح', 'weight' => 'غير متاح', 'length' => 'غير متاح'];
    foreach ($babySizes as $week => $info) {
        if ($currentWeek <= $week) {
            $babyInfo = $info;
            break;
        }
    }

    if ($currentWeek >= 1 && $currentWeek <= 13) {
        $imageStage = 1;
    } elseif ($currentWeek >= 14 && $currentWeek <= 27) {
        $imageStage = 2;
    } else {
        $imageStage = 3;
    }


    $result = [
        'last_period_date' => $lastPeriod->toDateString(),
        'due_date' => $dueDate->toDateString(),
        'current_week' => $currentWeek,
        'current_day' => $currentDay,
        'month_info' => $monthInfo,
        'baby_size' => $babyInfo['size'],
        'baby_weight' => $babyInfo['weight'],
        'baby_length' => $babyInfo['length'],
        'image_stage' => $imageStage,
    ];

    return PregnancyCalculation::create([
        'user_id' => $userId,
        'last_period_date' => $lastPeriod,
        'due_date' => $dueDate,
        'result' => $result,
    ]);
}


    public function storeVaccination($userId, array $data)
    {
        $data['user_id'] = $userId;
        return VaccinationSchedule::create($data);
    }
}
