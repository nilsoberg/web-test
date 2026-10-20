<?php

namespace App\Dto;

use Symfony\Component\Uid\Uuid;

class ParentTransferRequest
{
    public function __construct(
        public readonly ?string $target_form,
        public readonly ?int $parent_id,
        public readonly ?Uuid $parent_key,
        public readonly ?string $parent_subtype,
        public readonly ?string $parent_metadata = null,
        public readonly ?string $subtype_name = null,
        public readonly ?\App\Enum\SequenceType $parent_seq_type = null,
        public readonly ?string $suggested_name = null,
        public readonly ?string $parent_sequences = null,
    )
    {
    }

    public function toFormOptions(): array
    {
        if (!$this->parent_id || !$this->parent_key || !$this->target_form) {
            return [];
        }

        $data = [
            'parent_id' => $this->parent_id,
            'parent_key' => $this->parent_key->toRfc4122(),
        ];

        if ($this->parent_subtype !== null)
            $data['parent_subtype'] = $this->parent_subtype;
        if ($this->parent_metadata !== null)
            $data['parent_metadata'] = $this->parent_metadata;
        if ($this->subtype_name !== null)
            $data['parent_subtype_name'] = $this->subtype_name;
        if ($this->suggested_name !== null)
            $data['suggested_name'] = $this->suggested_name;
        if ($this->parent_sequences !== null && !empty($this->parent_sequences))
            $data['parent_sequences'] = $this->parent_sequences;
        if ($this->parent_seq_type !== null)
            $data['parent_seq_type'] = $this->parent_seq_type;

        return [
            $this->target_form => $data,
        ];
    }
}
