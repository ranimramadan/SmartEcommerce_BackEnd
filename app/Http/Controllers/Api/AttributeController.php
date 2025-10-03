<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use Illuminate\Http\Request;

class AttributeController extends Controller
{
    public function index(Request $request)
    {
        $q = Attribute::withCount('values');

        if ($s = $request->get('search')) {
            $q->where('name', 'like', "%{$s}%");
        }

        $q->orderBy('name', 'asc');

        $per = min((int) $request->get('per_page', 20), 100);
        return $q->paginate($per);
    }

    public function show(Attribute $attribute)
    {
        return $attribute->load('values');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|unique:attributes,name'
        ]);

        $attr = Attribute::create($data);
        return response()->json($attr, 201);
    }

    public function update(Request $request, Attribute $attribute)
    {
        $data = $request->validate([
            'name' => 'required|string|unique:attributes,name,' . $attribute->id
        ]);

        $attribute->update($data);
        return $attribute;
    }

    public function destroy(Attribute $attribute)
    {
        $attribute->delete();
        return response()->json(null, 204);
    }
}
