<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Http\Request;

class AttributeValueController extends Controller
{
    // GET /attributes/{attribute}/values
    public function index(Request $request, Attribute $attribute)
    {
        $q = $attribute->values()->orderBy('value');

        if ($s = $request->get('search')) {
            $q->where('value', 'like', "%{$s}%");
        }

        return $q->get();
    }

    // GET /values/{value}
    public function show(AttributeValue $value)
    {
        return $value->load('attribute');
    }

    // POST /attributes/{attribute}/values
    public function store(Request $request, Attribute $attribute)
    {
        $data = $request->validate([
            'value' => 'required|string|unique:attribute_values,value,NULL,id,attribute_id,'.$attribute->id
        ]);

        $val = $attribute->values()->create($data);
        return response()->json($val, 201);
    }

    // PUT /values/{value}
    public function update(Request $request, AttributeValue $value)
    {
        $data = $request->validate([
            'value' => 'required|string|unique:attribute_values,value,'.$value->id.',id,attribute_id,'.$value->attribute_id
        ]);

        $value->update($data);
        return $value;
    }

    // DELETE /values/{value}
    public function destroy(AttributeValue $value)
    {
        $value->delete();
        return response()->json(null, 204);
    }
}
