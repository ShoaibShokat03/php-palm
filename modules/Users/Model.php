<?php

namespace App\Modules\Users;

use App\Core\Model as BaseModel;
use Frontend\Palm\Validation\Attributes\Required;
use Frontend\Palm\Validation\Attributes\IsString;

class Model extends BaseModel
{
    protected string $table = 'users';
    
    public int $id;
    
    public string $role;
    public string $name;
    public string $email;
    public ?string $phone_number = null;
    public string $gender;
    public ?string $address = null;
    public ?string $note = null;

    public ?string $created_at = null;
    public ?string $updated_at = null;
}