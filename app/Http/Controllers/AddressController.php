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
            $user = Auth::user();
            $isDefault = $request->boolean('is_default');

            // Si se marca como default, desactivamos todas las demás
            if ($isDefault) {
                $user->addresses()->update(['is_default' => false]);
            } else {
                // Si no se marca como default, pero el usuario no tiene ninguna default, esta se vuelve default
                $hasDefault = $user->addresses()->where('is_default', true)->exists();
                if (!$hasDefault) {
                    $isDefault = true;
                }
            }

            $address = Address::create([
                'user_id' => $user->id,
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
                'is_default' => $isDefault,
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
            $user = Auth::user();

            $isDefault = $request->boolean('is_default', $address->is_default); // fallback al actual

            // Si ahora se marca como default y antes no lo era, actualizar las demás
            if ($isDefault && !$address->is_default) {
                $user->addresses()->update(['is_default' => false]);
            } else {
                // Si no se marca como default y no hay otra, forzarla como default
                $hasOtherDefault = $user->addresses()
                    ->where('id', '!=', $address->id)
                    ->where('is_default', true)
                    ->exists();

                if (!$isDefault && !$hasOtherDefault) {
                    $isDefault = true;
                }
            }

            $address->fill($request->validated());
            $address->is_default = $isDefault;
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

    public function setDefaultAddress($id)
    {
        $user = Auth::user();

        $address = $user->addresses()->where('id', $id)->first();

        if (!$address) {
            return $this->sendError('Dirección no encontrada.', [], 404);
        }

        // Desactivar la anterior predeterminada
        $user->addresses()->where('is_default', true)->update(['is_default' => false]);

        // Activar la nueva predeterminada
        $address->update(['is_default' => true]);

        return $this->sendResponse($address, 'Dirección establecida como predeterminada.');
    }
}
