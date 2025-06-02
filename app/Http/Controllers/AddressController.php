<?php

namespace App\Http\Controllers;

use App\Http\Requests\Address\StoreAddressRequest;
use App\Http\Requests\Address\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AddressController extends BaseController
{
    public function index()
    {
        $user = Auth::user();
        $addresses = Address::where('user_id', $user->id)->paginate();

        return $this->sendResponse(AddressResource::collection($addresses), 'Lista de direcciones obtenida exitosamente.');
    }

    public function store(StoreAddressRequest $request)
    {
        DB::beginTransaction();

        try {
            $address = Address::create([
                'user_id' => Auth::user()->id,
                'name' => $request->name,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'company' => $request->company,
                'document_type' => $request->document_type,
                'document_number' => $request->document_number,
                'fiscal_name' => $request->fiscal_name,
                'street_address' => $request->street_address,
                'apartment' => $request->apartment,
                'city' => $request->city,
                'state' => $request->state,
                'postal_code' => $request->postal_code,
                'country' => $request->country,
                'is_default' => $request->boolean('is_default'),
                'notes' => $request->notes,
            ]);

            DB::commit();

            return $this->sendResponse(['address' => new AddressResource($address)], 'Dirección creada exitosamente.', 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->sendError('Error al crear la dirección: ' . $e->getMessage(), [], 500);
        }
    }

    public function show(Address $address)
    {
        return $this->sendResponse(new AddressResource($address), 'Dirección obtenida exitosamente.');
    }

    public function update(UpdateAddressRequest $request, Address $address)
    {
        DB::beginTransaction();

        try {
            $address->fill($request->validated());
            $address->save();

            DB::commit();

            return $this->sendResponse(['address' => new AddressResource($address)], 'Dirección actualizada correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->sendError('Error al actualizar la dirección: ' . $e->getMessage(), [], 500);
        }
    }

    public function destroy(Address $address)
    {
        DB::beginTransaction();

        try {
            $address->delete();

            DB::commit();

            return $this->sendResponse([], 'Dirección eliminada correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->sendError('Error al eliminar la dirección: ' . $e->getMessage(), [],  500);
        }
    }
}
