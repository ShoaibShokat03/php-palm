<?php

namespace App\Modules\Users;

use App\Core\Model as BaseModel;
use Frontend\Palm\Validation\Attributes\Required;
use Frontend\Palm\Validation\Attributes\IsString;
use Frontend\Palm\Validation\Attributes\IsEmail;
use Frontend\Palm\Validation\Attributes\IsDate;
use Frontend\Palm\Validation\Attributes\Length;

/**
 * Users Model
 * 
 * Uses ActiveRecord pattern - no CRUD methods needed!
 * 
 * Usage:
 * - Model::all() - Get all records
 * - Model::where('status', 'active')->all() - Query with conditions
 * - Model::find(1) - Find by ID
 * - Model::create(['name' => 'John']) - Create new record
 * - $model->save() - Update record
 * - $model->delete() - Delete record
 * 
 * See ACTIVERECORD_USAGE.md for more examples
 */
class Model extends BaseModel
{
    protected string $table = 'users';

    // Model fields - defining properties enables Validation Attributes & IDE Autocomplete!

    // Primary Key (Optional to define, but good for clarity)
    public int $id;

    #[Required]
    #[IsString]
    #[Length(min: 3, max: 50)]
    public string $name;

    #[Required]
    #[IsEmail]
    public string $email;

    #[IsString]
    public string $role = 'user';

    #[IsDate]
    public ?string $created_at = null;
}
