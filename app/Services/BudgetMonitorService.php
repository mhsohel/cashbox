<?php

namespace App\Services;

use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BudgetMonitorService
{
    /**
     * Get budget pacing metrics and AI generated strategic advice.
     */
    public function getBudgetInsights($user): array
    {
        $month = Carbon::now()->format('Y-m');
        $startDate = Carbon::now()->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();
        $totalDays = Carbon::now()->daysInMonth;
        $daysPassed = max(1, Carbon::now()->day);

        $categories = $user->categories()->get();

        $categoriesData = $categories->map(function (Category $category) use ($startDate, $endDate, $month, $totalDays, $daysPassed) {
            // Get actual spent/earned in current month
            $spent = $category->transactions()
                ->where('type', 'expense')
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->sum('amount');

            $earned = $category->transactions()
                ->where('type', 'income')
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->sum('amount');

            $spent = (float) $spent;
            $earned = (float) $earned;

            // Find budget limit based on category type
            if ($category->type === 'expense') {
                $budget = $category->budgets()->whereNull('month')->first();
                $limit = $budget ? (float) $budget->amount : 0.0;
                $actual = $spent;
            } else {
                $budget = $category->budgets()->where('month', $month)->first();
                $limit = $budget ? (float) $budget->amount : 0.0;
                $actual = $earned;
            }

            // Pacing Mathematics
            $idealPace = $limit > 0 ? round($limit / $totalDays, 2) : 0.0;
            $actualPace = round($actual / $daysPassed, 2);
            $pacingRatio = $idealPace > 0 ? round(($actualPace / $idealPace) * 100, 2) : 0.0;

            if ($category->type === 'expense') {
                $paceSurplus = $limit > 0 ? round(($idealPace * $daysPassed) - $actual, 2) : 0.0;
                $remainingBudget = max(0.0, $limit - $actual);
                $isMismatched = ($limit > 0 && $actualPace > $idealPace);
            } else {
                $paceSurplus = $limit > 0 ? round($actual - ($idealPace * $daysPassed), 2) : 0.0;
                $remainingBudget = max(0.0, $limit - $actual);
                $isMismatched = ($limit > 0 && $actualPace < $idealPace);
            }

            return [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type,
                'color' => $category->color ?? '#3B82F6',
                'budget_limit' => $limit,
                'actual_amount' => $actual,
                'ideal_pace_per_day' => $idealPace,
                'actual_pace_per_day' => $actualPace,
                'pacing_ratio_percentage' => $pacingRatio,
                'pace_surplus' => $paceSurplus,
                'remaining_budget' => $remainingBudget,
                'is_mismatched' => $isMismatched,
            ];
        });

        // Filter out categories that have a budget limit configured
        $budgetedCategories = $categoriesData->filter(fn($c) => $c['budget_limit'] > 0)->values();
        
        // Mismatches represent categories that are over pacing (expenses) or under pacing (income targets)
        $mismatchedCategories = $budgetedCategories->filter(fn($c) => $c['is_mismatched'])->values();
        $hasMismatches = $mismatchedCategories->isNotEmpty();

        // Fetch or cache AI insights/advice
        $cacheKey = "user_{$user->id}_budget_monitor_insights_" . $month;
        
        // Cache Gemini API result payload per user for 6 hours
        $advice = Cache::remember($cacheKey, now()->addHours(6), function () use ($budgetedCategories, $daysPassed, $totalDays) {
            return $this->generateAiAdvice($budgetedCategories, $daysPassed, $totalDays);
        });

        return [
            'categories' => $categoriesData->values()->toArray(),
            'days_passed' => $daysPassed,
            'total_days' => $totalDays,
            'has_mismatches' => $hasMismatches,
            'ai_global_advice' => $advice,
            'current_month' => Carbon::now()->format('F Y'),
        ];
    }

    /**
     * Generate strategic pacing advice from Gemini.
     */
    protected function generateAiAdvice($categories, int $daysPassed, int $totalDays): string
    {
        $apiKey = config('services.gemini.key');
        $model = config('services.gemini.model', 'gemini-1.5-pro');

        if (empty($apiKey)) {
            Log::warning('Gemini API key is missing. Falling back to local rules.');
            return $this->generateLocalFallbackAdvice($categories);
        }

        // Only pass simple representation to the model to reduce token usage and keep prompt clean
        $payload = [
            'days_passed' => $daysPassed,
            'total_days' => $totalDays,
            'categories' => $categories->map(fn($c) => [
                'name' => $c['name'],
                'type' => $c['type'],
                'budget_limit' => $c['budget_limit'],
                'actual_amount' => $c['actual_amount'],
                'ideal_pace_per_day' => $c['ideal_pace_per_day'],
                'actual_pace_per_day' => $c['actual_pace_per_day'],
                'pacing_ratio_percentage' => $c['pacing_ratio_percentage'] . '%',
                'pace_surplus' => $c['pace_surplus'],
                'is_mismatched' => $c['is_mismatched'],
            ])->toArray()
        ];

        $prompt = "You are a financial advisor analyzing a user's monthly budget pacing.
Here is the data for the current month:
Days passed: {$daysPassed} out of {$totalDays} total days in the month.

Category Pacing Data:
" . json_encode($payload, JSON_PRETTY_PRINT) . "

Analyze this data and perform these tasks:
1. Identify category pacing mismatches (expenses spending too fast or income targets lagging behind).
2. Locate budget surpluses in healthy categories (positive pace_surplus).
3. Generate a concise, highly specific 2-3 sentence strategic advice block suggesting how to shift surplus dollars to save the failing categories. Reference specific categories and amounts in Bangladeshi Taka (৳).
4. Output must be a valid JSON object containing exactly one key: 'ai_global_advice'. Do not wrap the JSON in Markdown code block tags.

Example output:
{
  \"ai_global_advice\": \"Your Food category is pacing 45% faster than ideal with a deficit of ৳1,200. We recommend shifting ৳1,500 from your Entertainment category, which currently has a ৳2,000 surplus, to keep your budget balanced.\"
}";

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(10)->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                ]
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
                
                // Clean the output if the model wrapped it in markdown code block despite instructions
                $cleanText = preg_replace('/^```json\s*|```\s*$/', '', trim($text));
                $decoded = json_decode($cleanText, true);

                if (isset($decoded['ai_global_advice'])) {
                    return $decoded['ai_global_advice'];
                }

                Log::error('Gemini advice parsed key was not found', ['raw_response' => $text]);
            } else {
                Log::error('Gemini API request failed', ['status' => $response->status(), 'response' => $response->body()]);
            }
        } catch (\Exception $e) {
            Log::error('Exception calling Gemini API', ['message' => $e->getMessage()]);
        }

        return $this->generateLocalFallbackAdvice($categories);
    }

    /**
     * Local rule-based fallback advice.
     */
    protected function generateLocalFallbackAdvice($categories): string
    {
        $overPacedExpenses = $categories->filter(fn($c) => $c['is_mismatched'] && $c['type'] === 'expense');
        $surplusCategories = $categories->filter(fn($c) => !$c['is_mismatched'] && $c['type'] === 'expense' && $c['pace_surplus'] > 0);

        if ($overPacedExpenses->isEmpty()) {
            return "All budget categories are pacing well. Continue monitoring your expenses to maintain this healthy trend.";
        }

        $failingNames = $overPacedExpenses->map(fn($c) => $c['name'])->implode(', ');
        
        if ($surplusCategories->isNotEmpty()) {
            $surplusNames = $surplusCategories->map(fn($c) => $c['name'])->implode(', ');
            $totalSurplus = $surplusCategories->sum('pace_surplus');
            
            return "You are spending too fast in your {$failingNames} categories. We recommend reallocating surplus funds from your healthy {$surplusNames} categories (current surplus of ৳" . number_format($totalSurplus, 2) . ") to cover these deficits.";
        }

        return "You are spending too fast in your {$failingNames} categories. Consider lowering your daily transaction rate to bring your spending back under target limits.";
    }
}
