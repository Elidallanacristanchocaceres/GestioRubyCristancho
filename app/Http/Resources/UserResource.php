<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'cedula' => $this->cedula,
            'email' => $this->email,
            'role' => $this->role,
            'rol_label' => $this->rolLabel(),
            'es_admin' => $this->esAdmin(),
        ];
    }
}
