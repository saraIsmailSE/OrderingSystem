<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return[
            "name_en"=> $this->name_en,
            "name_ar"=> $this->name_ar,
            "attributes"=>unserialize($this->attributes),
            "sections"=> SectionResource::collection($this->whenLoaded('sections')),
        ];
    }
}
