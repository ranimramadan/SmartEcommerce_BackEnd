<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $q = Brand::query();

        if ($s = $request->get('search')) {
            $q->where('name', 'like', "%{$s}%");
        }

        $sort = $request->get('sort', 'name_asc');
        match ($sort) {
            'name_desc' => $q->orderBy('name', 'desc'),
            default     => $q->orderBy('name', 'asc'),
        };

        $per = min((int) $request->get('per_page', 15), 100);

        return $q->paginate($per);
    }

    public function show(Brand $brand)
    {
        return $brand->loadCount('products');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|unique:brands,name',
            'slug' => 'nullable|string|unique:brands,slug',
            'logo' => 'nullable|image|max:2048',
        ]);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('brands', 'public');
        }

        $brand = Brand::create($data);
        return response()->json($brand, 201);
    }

    public function update(Request $request, Brand $brand)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|unique:brands,name,' . $brand->id,
            'slug' => 'sometimes|string|unique:brands,slug,' . $brand->id,
            'logo' => 'nullable|image|max:2048',
        ]);

        if (isset($data['name']) && !isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        if ($request->hasFile('logo')) {
            if ($brand->logo) Storage::disk('public')->delete($brand->logo);
            $data['logo'] = $request->file('logo')->store('brands', 'public');
        }

        $brand->update($data);
        return $brand;
    }

    public function destroy(Brand $brand)
    {
        if ($brand->logo) Storage::disk('public')->delete($brand->logo);
        $brand->delete();
        return response()->json(null, 204);
    }
}
