<?php

namespace App\Http\Requests\Master;

/**
 * Archive and restore carry no payload — they still need the master-data gate, which is
 * the whole reason this is a Form Request rather than an inline check.
 */
class MasterActionRequest extends MasterDataRequest
{
    public function rules(): array
    {
        return [];
    }
}
