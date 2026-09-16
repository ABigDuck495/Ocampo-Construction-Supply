<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        
        try {
            $groupedSettings = SystemSetting::grouped();
        
            // also expose settings map to JS like other pages
            $systemSettings = \Illuminate\Support\Facades\DB::table('System_Settings')
                ->pluck('Setting_Value', 'Setting_Key')
                ->toArray();

            return view('settings.index', [
                'groupedSettings' => $groupedSettings,
                'systemSettings' => $systemSettings,
            ]);
        } catch (\Throwable $e) {
            Log::error('Settings page failed: ' . $e->getMessage(), ['exception' => $e]);

            // Return the view with an empty collection and show a user-friendly message
            return view('settings.index', [
                'groupedSettings' => collect(),
            ])->with('status', 'Could not load system settings (see logs).');
        }
    }

    public function update(Request $request): RedirectResponse
    {
        $allSettings = SystemSetting::query()->get()->keyBy('setting_key');

        $submitted = (array) $request->input('settings', []);

        $rules = [];
        foreach ($allSettings as $key => $setting) {
            if ($setting->inputType() === 'number') {
                $rules["settings.$key"] = 'nullable|numeric';
            } elseif ($setting->inputType() === 'boolean') {
                // checkboxes: no rule needed, absence is valid (= false)
                continue;
            } else {
                $rules["settings.$key"] = 'nullable|string|max:255';
            }
        }

        $validator = Validator::make($request->all(), $rules);
        $validator->validate();

        foreach ($allSettings as $key => $setting) {
            if ($setting->inputType() === 'boolean') {
                $value = array_key_exists($key, $submitted) ? 'true' : 'false';
            } else {
                // Fall back to the existing value if the field was omitted.
                $value = $submitted[$key] ?? $setting->setting_value;
            }

            if ($value !== $setting->setting_value) {
                $setting->setting_value = $value;
                $setting->save();
            }
        }

        return redirect()
            ->route('settings.index')
            ->with('status', 'Settings updated successfully.');
    }
}