<?php

/**
 * Module Scaffolding Configuration
 * 
 * Configuration for the make:module CLI command.
 * 
 * @package PHPFrarm
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Base Namespace
    |--------------------------------------------------------------------------
    |
    | The base namespace for generated modules.
    |
    */
    'namespace' => 'PHPFrarm\\Modules',

    /*
    |--------------------------------------------------------------------------
    | Author Information
    |--------------------------------------------------------------------------
    |
    | Default author information for generated files.
    |
    */
    'author' => 'PHPFrarm Team',
    'author_email' => 'dev@phpfrarm.local',

    /*
    |--------------------------------------------------------------------------
    | Default Features
    |--------------------------------------------------------------------------
    |
    | Features to include by default when creating a new module.
    |
    */
    'default_features' => [
        'api',      // Generate API controller
        'crud',     // Generate CRUD operations
    ],

    /*
    |--------------------------------------------------------------------------
    | Stubs Directory
    |--------------------------------------------------------------------------
    |
    | Directory containing custom file templates.
    | If not set, built-in templates are used.
    |
    */
    'stubs_path' => null,

    /*
    |--------------------------------------------------------------------------
    | Table Naming Convention
    |--------------------------------------------------------------------------
    |
    | How table names are generated from module names.
    | Options: snake_case, plural_snake_case
    |
    */
    'table_naming' => 'snake_case',

    /*
    |--------------------------------------------------------------------------
    | Primary Key Type
    |--------------------------------------------------------------------------
    |
    | Default primary key type for generated tables.
    | Options: ulid, uuid, auto_increment
    |
    */
    'primary_key_type' => 'ulid',

    /*
    |--------------------------------------------------------------------------
    | Soft Delete
    |--------------------------------------------------------------------------
    |
    | Include soft delete support in generated modules.
    |
    */
    'soft_delete' => true,

    /*
    |--------------------------------------------------------------------------
    | Timestamps
    |--------------------------------------------------------------------------
    |
    | Include created_at and updated_at columns.
    |
    */
    'timestamps' => true,

    /*
    |--------------------------------------------------------------------------
    | Optimistic Locking
    |--------------------------------------------------------------------------
    |
    | Include version column for optimistic locking.
    |
    */
    'optimistic_locking' => true,

    /*
    |--------------------------------------------------------------------------
    | Generate Tests
    |--------------------------------------------------------------------------
    |
    | Automatically generate test files.
    |
    */
    'generate_tests' => true,

    /*
    |--------------------------------------------------------------------------
    | Generate MongoDB Indexes
    |--------------------------------------------------------------------------
    |
    | Generate MongoDB index files for logging.
    |
    */
    'generate_mongo_indexes' => false,

    /*
    |--------------------------------------------------------------------------
    | Default Fields
    |--------------------------------------------------------------------------
    |
    | Default fields included in every generated module.
    | Format: field_name => [type, nullable, default]
    |
    */
    'default_fields' => [
        'name' => ['type' => 'VARCHAR(255)', 'nullable' => false],
        'description' => ['type' => 'TEXT', 'nullable' => true],
        'status' => ['type' => "ENUM('active', 'inactive', 'draft')", 'nullable' => false, 'default' => 'active'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Status Options
    |--------------------------------------------------------------------------
    |
    | Default status enum values.
    |
    */
    'status_options' => ['active', 'inactive', 'draft'],

    /*
    |--------------------------------------------------------------------------
    | Stored Procedures
    |--------------------------------------------------------------------------
    |
    | Stored procedures to generate for each module.
    |
    */
    'stored_procedures' => [
        'find_all',
        'find_by_id',
        'create',
        'update',
        'soft_delete',
        'hard_delete',
        'restore',
        'count',
    ],
];
