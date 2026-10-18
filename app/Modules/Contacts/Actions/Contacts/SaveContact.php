<?php

namespace App\Modules\Contacts\Actions\Contacts;

use App\Modules\Contacts\Data\ContactData;
use App\Modules\Contacts\Models\Contact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaveContact
{
    /**
     * Create a new contact or update an existing one. A contact saved without
     * a code gets `C-00042` from its id.
     */
    public function handle(Contact $contact, ContactData $data): Contact
    {
        return DB::transaction(function () use ($contact, $data): Contact {
            $attributes = $data->toArray();
            $code = filled($data->code) ? (string) $data->code : null;

            $contact->fill([
                ...$attributes,
                'code' => $code ?? ($contact->exists ? $contact->code : 'new-'.Str::random(20)),
            ])->save();

            if ($code === null && str_starts_with($contact->code, 'new-')) {
                $contact->update(['code' => $this->generatedCode($contact)]);
            }

            return $contact;
        });
    }

    /**
     * The code from the id, with a suffix if someone already typed it by hand.
     */
    protected function generatedCode(Contact $contact): string
    {
        $base = sprintf('C-%05d', $contact->id);
        $code = $base;
        $suffix = 1;

        while (Contact::query()->where('code', $code)->whereKeyNot($contact->id)->exists()) {
            $code = "{$base}-".$suffix++;
        }

        return $code;
    }
}
