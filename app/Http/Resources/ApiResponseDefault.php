<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiResponseDefault extends JsonResource
{
    // Deklarasikan Variabel
    public $success;
    public $message;
    public $resource;
    public $status;

    // Membuat Konstruktor
    public function __construct($success, $message, $resource=null, $status=200)
    {
        $this->success = $success;
        $this->message = $message;
        parent::__construct($resource);
        $this->status = $status;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'success'   => $this->success,
            'message'   => $this->message,
            'data'      => parent::toArray($request),
        ];
    }

    public function withResponse($request, $response)
    {
        $response->setStatusCode($this->status);
    }
}
