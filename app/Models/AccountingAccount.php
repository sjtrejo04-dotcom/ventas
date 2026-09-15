<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'description', 'nature', 'is_auxiliary'])]
class AccountingAccount extends Model
{
    //
}
