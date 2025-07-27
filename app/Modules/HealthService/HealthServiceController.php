<?php


namespace App\Modules\HealthService;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\HealthService\Services\HealthService;

class HealthServiceController extends Controller
{
    protected $healthService;

    public function __construct(HealthService $healthService)
    {
        $this->healthService = $healthService;
    }




    public function getOvulationResults()
    {
        $results = $this->service->getAllOvulationResults();
        return response()->json(['data' => $results]);
    }

    public function getBodyWeightResults()
    {
        $results = $this->service->getAllBodyWeightResults();
        return response()->json(['data' => $results]);
    }

    public function getBloodSugarResults()
    {
        $results = $this->service->getAllBloodSugarResults();
        return response()->json(['data' => $results]);
    }

    public function getBloodPressureResults()
    {
        $results = $this->service->getAllBloodPressureResults();
        return response()->json(['data' => $results]);
    }

    public function getPregnancyCalculations()
    {
        $results = $this->service->getAllPregnancyCalculations();
        return response()->json(['data' => $results]);
    }

    public function getVaccinationSchedules()
    {
        $results = $this->service->getAllVaccinationSchedules();
        return response()->json(['data' => $results]);
    }

public function storeOvulation(Request $request)
{
    $validated = $request->validate([
        'start_day_of_cycle' => 'required|date',
        'cycle_length' => 'required|integer|min:21|max:35',
        'period_length' => 'required|integer|min:3|max:10',
    ]);

    return response()->json(
        $this->healthService->calculateOvulation($request->user()->id, $validated)
    );
}



    public function storeBodyWeight(Request $request)
    {
        $data = $request->only(['height', 'weight', 'unit']);
        return response()->json(
            $this->healthService->calculateBodyWeight($request->user()->id, $data)
        );
    }

    public function storeBloodSugar(Request $request)
    {
        $data = $request->only(['value', 'condition_type', 'measured_at']);
        return response()->json(
            $this->healthService->storeBloodSugar($request->user()->id, $data)
        );
    }

    public function storeBloodPressure(Request $request)
    {
        $data = $request->only(['systolic', 'diastolic', 'condition_type', 'measured_at']);
        return response()->json(
            $this->healthService->storeBloodPressure($request->user()->id, $data)
        );
    }

   public function storePregnancy(Request $request)
{
    $data = $request->only(['last_period_date']);
    return response()->json(
        $this->healthService->calculatePregnancy($request->user()->id, $data)
    );
}

    public function storeVaccination(Request $request)
    {
        $data = $request->only(['child_name', 'gender', 'birth_date', 'schedule']);
        return response()->json(
            $this->healthService->storeVaccination($request->user()->id, $data)
        );
    }
}
