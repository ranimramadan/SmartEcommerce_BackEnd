<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\SettingsService;

class SettingsController extends Controller
{
    public function __construct(protected SettingsService $settings) {}

    public function index(Request $request)
    {
        $group = $request->query('group');
        return response()->json($this->settings->list($group));
    }

    public function upsertMany(Request $request)
    {
        $validated = $request->validate([
            '*.key'   => 'required|string|max:255',
            '*.value' => 'nullable',
            '*.group' => 'nullable|string|max:100',
        ]);
        $this->settings->upsertMany($validated);
        return response()->json($this->settings->list());
    }

    public function show(string $fullKey)
    {
        return response()->json([
            'key'   => $fullKey,
            'value' => $this->settings->get($fullKey),
        ]);
    }

    public function update(Request $request, string $fullKey)
    {
        $data = $request->validate([
            'value' => 'nullable',
            'group' => 'nullable|string|max:100',
            'public'=> 'nullable|boolean',
        ]);
        $this->settings->set($fullKey, $data['value'] ?? null, $data['group'] ?? null, (bool)($data['public'] ?? false));
        return response()->json([
            'key'   => $fullKey,
            'value' => $this->settings->get($fullKey),
        ]);
    }
}
