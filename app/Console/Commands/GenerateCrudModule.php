<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateCrudModule extends Command
{
    protected $signature = 'make:crud-module 
                            {name : The name of the module (singular)}
                            {--fields= : Field definitions in JSON format or comma-separated}
                            {--interactive : Interactive mode to define fields step by step}';

    protected $description = 'Generate a complete CRUD module with Model, Service, Livewire Component, and Views with dynamic form fields';

    protected array $fields = [];

    public function handle(): int
    {
        $name = Str::singular($this->argument('name'));
        $plural = Str::plural($name);
        $lowerName = Str::lower($name);
        $lowerPlural = Str::lower($plural);
        $studlyName = Str::studly($name);
        $studlyPlural = Str::studly($plural);

        $this->info("Generating CRUD module for: {$studlyName}");

        // Collect field definitions
        $this->collectFields();

        // Create Model
        $this->createModel($studlyName, $lowerName, $lowerPlural);

        // Create Service
        $this->createService($studlyName, $lowerName, $lowerPlural);

        // Create Livewire Component
        $this->createComponent($studlyName, $lowerName, $lowerPlural, $studlyPlural);

        // Create View
        $this->createView($studlyName, $lowerName, $lowerPlural, $studlyPlural);

        // Create View Component
        $this->createViewComponent($studlyName, $lowerName, $lowerPlural, $studlyPlural);

        // Create View Page
        $this->createViewPage($studlyName, $lowerName, $lowerPlural, $studlyPlural);

        // Create Migration
        $this->call('make:migration', [
            'name' => "create_{$lowerPlural}_table",
        ]);

        // Add module to ModuleSeeder
        $this->addModuleToSeeder($studlyName, $lowerPlural);

        $this->info("✅ CRUD module '{$studlyName}' generated successfully!");
        $this->info("📝 Don't forget to:");
        $this->info("   1. Update the migration file with the field definitions");
        $this->info("   2. Add route in app/Src/Admin/Infrastructure/routes.php:");
        $this->info("      Route::get('{$lowerPlural}/{id}/view', {$studlyName}View::class)->name('{$lowerPlural}.view');");
        $this->info("   3. Add use statement: use App\\Src\\Admin\\Modules\\{$studlyName}\\{$studlyName}View;");
        $this->info("   4. Run migration: php artisan migrate");
        $this->info("   5. Run seeder: php artisan db:seed --class=ModuleSeeder");

        return Command::SUCCESS;
    }

    protected function collectFields(): void
    {
        // Add default status field
        $this->fields[] = [
            'name' => 'status',
            'type' => 'checkbox',
            'label' => 'Status',
            'required' => false,
            'default' => true,
        ];

        if ($this->option('interactive')) {
            $this->collectFieldsInteractively();
        } elseif ($this->option('fields')) {
            $this->parseFieldsOption();
        } else {
            // Default: just name field
            $this->fields[] = [
                'name' => 'name',
                'type' => 'text',
                'label' => 'Name',
                'required' => true,
            ];
        }
    }

    protected function collectFieldsInteractively(): void
    {
        $this->info("\n📝 Define form fields (press Enter with empty name to finish):\n");

        while (true) {
            $fieldName = $this->ask('Field name (snake_case, e.g., customer_name, gender)', '');
            
            if (empty($fieldName)) {
                break;
            }

            $fieldLabel = $this->ask('Field label', Str::title(str_replace('_', ' ', $fieldName)));
            
            $fieldType = $this->choice(
                'Field type',
                [
                    'text', 'textarea', 'number', 'email', 'password', 'url', 'tel',
                    'select', 'radio', 'checkbox', 'date', 'datetime', 'time',
                    'file', 'image', 'color', 'range', 'hidden'
                ],
                'text'
            );

            $required = $this->confirm('Is this field required?', true);

            $field = [
                'name' => $fieldName,
                'type' => $fieldType,
                'label' => $fieldLabel,
                'required' => $required,
            ];

            // Handle options for select and radio
            if (in_array($fieldType, ['select', 'radio'])) {
                $optionsInput = $this->ask('Options (comma-separated, e.g., male,female or active:Active,inactive:Inactive)');
                if (!empty($optionsInput)) {
                    $field['options'] = $this->parseOptions($optionsInput);
                }
            }

            // Handle foreign key relationships
            if ($fieldType === 'select' && $this->confirm('Is this a foreign key relationship?', false)) {
                $field['foreign_key'] = true;
                $field['related_model'] = $this->ask('Related model name (e.g., Category, User)', '');
                $field['related_display'] = $this->ask('Display field from related model (e.g., name, title)', 'name');
            }

            // Handle file/image upload settings
            if (in_array($fieldType, ['file', 'image'])) {
                $field['accept'] = $fieldType === 'image' ? 'image/*' : $this->ask('Accepted file types (e.g., image/*, .pdf, .doc)', '*');
                $field['max_size'] = $this->ask('Max file size in KB (default: 2048)', '2048');
                $field['multiple'] = $this->confirm('Allow multiple files?', false);
            }

            // Handle range slider
            if ($fieldType === 'range') {
                $field['min'] = $this->ask('Minimum value', '0');
                $field['max'] = $this->ask('Maximum value', '100');
                $field['step'] = $this->ask('Step value', '1');
            }

            // Handle default value
            if ($fieldType === 'checkbox') {
                $field['default'] = $this->confirm('Default checked?', false);
            } elseif ($fieldType === 'number' || $fieldType === 'range') {
                $defaultValue = $this->ask('Default value (number)', '0');
                $field['default'] = is_numeric($defaultValue) ? (float)$defaultValue : 0;
            } elseif ($fieldType === 'color') {
                $defaultValue = $this->ask('Default color (hex, e.g., #000000)', '#000000');
                $field['default'] = $defaultValue;
            } elseif ($fieldType !== 'file' && $fieldType !== 'image' && $fieldType !== 'hidden') {
                $defaultValue = $this->ask('Default value (leave empty for none)', '');
                if (!empty($defaultValue)) {
                    $field['default'] = $defaultValue;
                }
            }

            $this->fields[] = $field;
            $this->info("✅ Added field: {$fieldName} ({$fieldType})\n");
        }
    }

    protected function parseFieldsOption(): void
    {
        $fieldsInput = $this->option('fields');
        
        // Try to parse as JSON first
        $fields = json_decode($fieldsInput, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($fields)) {
            $this->fields = array_merge($this->fields, $fields);
            return;
        }

        // Parse as simple format: field1:type,field2:type:options
        // Handle complex cases like: gender:radio:male:Male,female:Female
        // We need to split by comma first, but be careful with options that contain commas
        $fieldStrings = $this->parseFieldString($fieldsInput);
        
        foreach ($fieldStrings as $fieldString) {
            $fieldString = trim($fieldString);
            if (empty($fieldString)) {
                continue;
            }

            // Split by colon, but limit to 3 parts: name:type:options
            $parts = explode(':', $fieldString, 3);
            $fieldName = $parts[0] ?? '';
            
            if (empty($fieldName)) {
                continue;
            }

            $fieldType = $parts[1] ?? 'text';
            $options = $parts[2] ?? null;

            $field = [
                'name' => $fieldName,
                'type' => $fieldType,
                'label' => Str::title(str_replace('_', ' ', $fieldName)),
                'required' => true,
            ];

            if ($options && in_array($fieldType, ['select', 'radio'])) {
                $field['options'] = $this->parseOptions($options);
            }

            $this->fields[] = $field;
        }
    }

    protected function parseFieldString(string $input): array
    {
        // Smart parsing: field1:type,field2:type:options
        // Handle: gender:radio:male:Male,female:Female
        // Split by comma first, then merge options back
        
        $fields = [];
        $parts = explode(',', $input);
        $i = 0;
        $validFieldTypes = ['text', 'textarea', 'number', 'email', 'password', 'url', 'tel', 'select', 'radio', 'checkbox', 'date', 'datetime', 'time', 'file', 'image', 'color', 'range', 'hidden'];
        
        while ($i < count($parts)) {
            $part = trim($parts[$i]);
            $fieldParts = explode(':', $part);
            
            if (count($fieldParts) < 2) {
                $i++;
                continue;
            }
            
            $fieldType = $fieldParts[1] ?? '';
            
            // Check if this is a field definition (has valid type)
            if (in_array($fieldType, $validFieldTypes)) {
                // If this is select/radio and has 3+ parts, it already has options
                // If it has exactly 2 parts, check if next parts are options
                if (in_array($fieldType, ['select', 'radio'])) {
                    if (count($fieldParts) >= 3) {
                        // Already has options, but might have more after comma
                        // Collect additional option parts
                        $options = [implode(':', array_slice($fieldParts, 2))];
                        $j = $i + 1;
                        
                        while ($j < count($parts)) {
                            $nextPart = trim($parts[$j]);
                            $nextParts = explode(':', $nextPart);
                            
                            // If next part is a new field definition, stop
                            if (count($nextParts) >= 2 && in_array($nextParts[1] ?? '', $validFieldTypes)) {
                                break;
                            }
                            
                            // This is an option part
                            $options[] = $nextPart;
                            $j++;
                        }
                        
                        // Reconstruct: field:type:all_options
                        $part = $fieldParts[0] . ':' . $fieldType . ':' . implode(',', $options);
                        $i = $j;
                    } else {
                        // No options yet, collect from next parts
                        $options = [];
                        $j = $i + 1;
                        
                        while ($j < count($parts)) {
                            $nextPart = trim($parts[$j]);
                            $nextParts = explode(':', $nextPart);
                            
                            // If next part is a new field definition, stop
                            if (count($nextParts) >= 2 && in_array($nextParts[1] ?? '', $validFieldTypes)) {
                                break;
                            }
                            
                            // This is an option part
                            $options[] = $nextPart;
                            $j++;
                        }
                        
                        if (!empty($options)) {
                            $part .= ':' . implode(',', $options);
                        }
                        $i = $j;
                    }
                } else {
                    // Regular field, move to next
                    $i++;
                }
            } else {
                // Not a field definition, might be part of previous field's options
                // This shouldn't happen if parsing is correct, but handle it
                $i++;
                continue;
            }
            
            $fields[] = $part;
        }
        
        return $fields;
    }

    protected function parseOptions(string $optionsInput): array
    {
        $options = [];
        $pairs = explode(',', $optionsInput);
        
        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if (strpos($pair, ':') !== false) {
                [$value, $label] = explode(':', $pair, 2);
                $options[trim($value)] = trim($label);
            } else {
                $options[$pair] = Str::title($pair);
            }
        }
        
        return $options;
    }

    protected function createModel(string $name, string $lowerName, string $lowerPlural): void
    {
        $path = app_path("Models/{$name}.php");
        
        if (File::exists($path)) {
            $this->warn("Model already exists: {$path}");
            return;
        }

        $stub = File::get(__DIR__ . '/stubs/model.stub');
        
        // Generate fillable fields
        $fillableFields = array_map(fn($field) => "'{$field['name']}'", $this->fields);
        $fillable = "        " . implode(",\n        ", $fillableFields);
        
        // Generate casts
        $casts = [];
        foreach ($this->fields as $field) {
            if ($field['type'] === 'checkbox') {
                $casts[] = "'{$field['name']}' => 'boolean'";
            } elseif ($field['type'] === 'number') {
                $casts[] = "'{$field['name']}' => 'decimal:2'";
            } elseif ($field['type'] === 'date') {
                $casts[] = "'{$field['name']}' => 'date'";
            } elseif ($field['type'] === 'datetime') {
                $casts[] = "'{$field['name']}' => 'datetime'";
            }
        }
        $castsString = $casts ? "        " . implode(",\n        ", $casts) : "        // No casts needed";
        
        $stub = str_replace(
            ['{{Name}}', '{{lowerName}}', '{{fillable}}', '{{casts}}'],
            [$name, $lowerName, $fillable, $castsString],
            $stub
        );

        File::put($path, $stub);
        $this->info("✅ Created Model: {$path}");
    }

    protected function createService(string $name, string $lowerName, string $lowerPlural): void
    {
        $path = app_path("Services/{$name}Service.php");
        
        if (File::exists($path)) {
            $this->warn("Service already exists: {$path}");
            return;
        }

        $stub = File::get(__DIR__ . '/stubs/service.stub');
        
        // Generate validation rules
        $rules = [];
        foreach ($this->fields as $field) {
            $rule = $this->generateValidationRule($field, $lowerPlural);
            if ($rule) {
                $rules[] = "            '{$field['name']}' => '{$rule}',";
            }
        }
        $rulesString = implode("\n", $rules);
        
        $stub = str_replace(
            ['{{Name}}', '{{lowerName}}', '{{lowerPlural}}', '{{validationRules}}'],
            [$name, $lowerName, $lowerPlural, $rulesString],
            $stub
        );

        File::put($path, $stub);
        $this->info("✅ Created Service: {$path}");
    }

    protected function generateValidationRule(array $field, string $table): string
    {
        $rules = [];
        
        if ($field['required'] ?? false) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }
        
        switch ($field['type']) {
            case 'text':
            case 'textarea':
                $rules[] = 'string';
                $rules[] = 'max:255';
                break;
            case 'number':
            case 'range':
                $rules[] = 'numeric';
                $rules[] = 'min:0';
                break;
            case 'email':
                $rules[] = 'email';
                $rules[] = 'max:255';
                break;
            case 'password':
                $rules[] = 'string';
                $rules[] = 'min:8';
                break;
            case 'url':
                $rules[] = 'url';
                $rules[] = 'max:255';
                break;
            case 'tel':
                $rules[] = 'string';
                $rules[] = 'max:20';
                break;
            case 'checkbox':
                $rules = ['boolean'];
                break;
            case 'select':
            case 'radio':
                if (isset($field['foreign_key']) && $field['foreign_key']) {
                    $relatedTable = Str::plural(Str::snake($field['related_model'] ?? ''));
                    $rules[] = "exists:{$relatedTable},id";
                } else {
                    $rules[] = 'string';
                }
                break;
            case 'date':
                $rules[] = 'nullable';
                $rules[] = 'string';
                break;
            case 'datetime':
                $rules[] = 'nullable';
                $rules[] = 'string';
                break;
            case 'time':
                $rules[] = 'date_format:H:i';
                break;
            case 'file':
                $rules[] = 'file';
                if (isset($field['max_size'])) {
                    $maxSize = (int)($field['max_size'] ?? 2048);
                    $rules[] = "max:{$maxSize}";
                }
                break;
            case 'image':
                $rules[] = 'image';
                if (isset($field['max_size'])) {
                    $maxSize = (int)($field['max_size'] ?? 2048);
                    $rules[] = "max:{$maxSize}";
                }
                break;
            case 'color':
                $rules[] = 'string';
                $rules[] = 'regex:/^#[0-9A-Fa-f]{6}$/';
                break;
            case 'hidden':
                $rules[] = 'string';
                break;
        }
        
        return implode('|', $rules);
    }

    protected function createComponent(string $name, string $lowerName, string $lowerPlural, string $studlyPlural): void
    {
        $path = app_path("Src/Admin/Modules/{$name}/{$name}Management.php");
        $dir = dirname($path);

        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        if (File::exists($path)) {
            $this->warn("Component already exists: {$path}");
            return;
        }

        $stub = File::get(__DIR__ . '/stubs/component.stub');
        
        // Generate form field properties
        $properties = [];
        foreach ($this->fields as $field) {
            $properties[] = $this->generatePropertyDeclaration($field);
        }
        $propertiesString = "    // Form fields\n" . implode("\n", $properties);
        
        // Generate validation rules
        $validationRules = [];
        foreach ($this->fields as $field) {
            if ($field['type'] === 'date') {
                $required = ($field['required'] ?? false) ? 'required' : 'nullable';
                $validationRules[] = "            '{$field['name']}' => ['{$required}', function (\$attribute, \$value, \$fail) {
                if (\$value && !preg_match('/^\\d{2}\\/\\d{2}\\/\\d{4}\$/', \$value)) {
                    try {
                        \\Carbon\\Carbon::createFromFormat('d/m/Y', \$value);
                    } catch (\\Exception \$e) {
                        \$fail('The {$field['label']} must be in dd/mm/yyyy format.');
                    }
                }
            }],";
            } elseif ($field['type'] === 'datetime') {
                $required = ($field['required'] ?? false) ? 'required' : 'nullable';
                $validationRules[] = "            '{$field['name']}' => ['{$required}', function (\$attribute, \$value, \$fail) {
                if (\$value && !preg_match('/^\\d{2}\\/\\d{2}\\/\\d{4} \\d{2}:\\d{2}\$/', \$value)) {
                    try {
                        \\Carbon\\Carbon::createFromFormat('d/m/Y H:i', \$value);
                    } catch (\\Exception \$e) {
                        \$fail('The {$field['label']} must be in dd/mm/yyyy HH:mm format.');
                    }
                }
            }],";
            } else {
                $rule = $this->generateValidationRule($field, $lowerPlural);
                if ($rule) {
                    $validationRules[] = "            '{$field['name']}' => '{$rule}',";
                }
            }
        }
        $validationRulesString = implode("\n", $validationRules);
        
        // Generate form data
        $formData = [];
        foreach ($this->fields as $field) {
            if ($field['type'] === 'password') {
                // Only include password if it's not empty
                $formData[] = "            '{$field['name']}' => !empty(\$this->{$field['name']}) ? \$this->{$field['name']} : null,";
            } elseif (in_array($field['type'], ['file', 'image'])) {
                // File uploads handled separately
                $formData[] = "            // '{$field['name']}' => \$this->{$field['name']}, // Handle file upload separately";
            } elseif ($field['type'] === 'date') {
                $formData[] = "            '{$field['name']}' => \$this->{$field['name']} ? (function() {
                try {
                    return \\Carbon\\Carbon::createFromFormat('d/m/Y', \$this->{$field['name']})->format('Y-m-d');
                } catch (\\Exception \$e) {
                    return \$this->{$field['name']};
                }
            })() : null,";
            } elseif ($field['type'] === 'datetime') {
                $formData[] = "            '{$field['name']}' => \$this->{$field['name']} ? (function() {
                try {
                    return \\Carbon\\Carbon::createFromFormat('d/m/Y H:i', \$this->{$field['name']})->format('Y-m-d H:i:s');
                } catch (\\Exception \$e) {
                    return \$this->{$field['name']};
                }
            })() : null,";
            } else {
                $formData[] = "            '{$field['name']}' => \$this->{$field['name']},";
            }
        }
        $formDataString = implode("\n", $formData);
        
        // Generate setFormData
        $setFormData = [];
        foreach ($this->fields as $field) {
            if ($field['type'] === 'checkbox') {
                $setFormData[] = "        \$this->{$field['name']} = \$model->{$field['name']} ?? false;";
            } elseif (in_array($field['type'], ['number', 'range'])) {
                $setFormData[] = "        \$this->{$field['name']} = \$model->{$field['name']} ?? 0.0;";
            } elseif (in_array($field['type'], ['file', 'image'])) {
                // File uploads are handled separately, don't set from model
                $setFormData[] = "        // \$this->{$field['name']} = null; // File uploads handled separately";
            } elseif ($field['type'] === 'password') {
                // Don't set password in edit mode
                $setFormData[] = "        // \$this->{$field['name']} = ''; // Password not loaded in edit mode";
            } elseif ($field['type'] === 'date') {
                $setFormData[] = "        \$this->{$field['name']} = \$model->{$field['name']} ? \$model->{$field['name']}->format('d/m/Y') : null;";
            } elseif ($field['type'] === 'datetime') {
                $setFormData[] = "        \$this->{$field['name']} = \$model->{$field['name']} ? \$model->{$field['name']}->format('d/m/Y H:i') : null;";
            } elseif ($field['required'] ?? false) {
                $setFormData[] = "        \$this->{$field['name']} = \$model->{$field['name']} ?? '';";
            } else {
                $setFormData[] = "        \$this->{$field['name']} = \$model->{$field['name']} ?? null;";
            }
        }
        $setFormDataString = implode("\n", $setFormData);
        
        // Generate resetForm
        $resetForm = [];
        foreach ($this->fields as $field) {
            $default = $this->getDefaultValue($field);
            $resetForm[] = "        \$this->{$field['name']} = {$default};";
        }
        $resetFormString = implode("\n", $resetForm);
        
        // Generate search fields
        $searchFields = array_filter($this->fields, fn($f) => in_array($f['type'], ['text', 'textarea', 'email', 'url', 'tel']) && $f['name'] !== 'password');
        $searchConditions = [];
        $firstField = true;
        foreach ($searchFields as $field) {
            if ($firstField) {
                $searchConditions[] = "            \$q->where('{$field['name']}', 'like', \"%{\$this->search}%\")";
                $firstField = false;
            } else {
                $searchConditions[] = "              ->orWhere('{$field['name']}', 'like', \"%{\$this->search}%\")";
            }
        }
        $searchString = !empty($searchConditions) 
            ? implode("\n", $searchConditions) . ";"
            : "            \$q->where('id', 'like', \"%{\$this->search}%\");";
        
        // Generate columns
        $columns = [];
        $columns[] = "            ['field' => 'id', 'label' => 'ID', 'type' => 'left'],";
        foreach ($this->fields as $field) {
            // Skip hidden fields, password, and file/image from table display
            if (!in_array($field['name'], ['status', 'password']) 
                && !in_array($field['type'], ['hidden', 'file', 'image', 'password'])) {
                $columns[] = "            ['field' => '{$field['name']}', 'label' => '{$field['label']}', 'type' => 'left'],";
            }
        }
        $columns[] = "            ['field' => 'status', 'label' => 'Status', 'type' => 'status'],";
        $columnsString = implode("\n", $columns);
        
        $stub = str_replace(
            [
                '{{Name}}', 
                '{{lowerName}}', 
                '{{lowerPlural}}', 
                '{{studlyPlural}}',
                '{{properties}}',
                '{{validationRules}}',
                '{{formData}}',
                '{{setFormData}}',
                '{{resetForm}}',
                '{{searchConditions}}',
                '{{columns}}',
            ],
            [
                $name, 
                $lowerName, 
                $lowerPlural, 
                $studlyPlural,
                $propertiesString,
                $validationRulesString,
                $formDataString,
                $setFormDataString,
                $resetFormString,
                $searchString,
                $columnsString,
            ],
            $stub
        );

        File::put($path, $stub);
        $this->info("✅ Created Component: {$path}");
    }

    protected function generatePropertyDeclaration(array $field): string
    {
        $type = match($field['type']) {
            'number', 'range' => 'float',
            'checkbox' => 'bool',
            'file', 'image' => 'mixed', // Livewire file uploads
            'textarea', 'text', 'email', 'password', 'url', 'tel', 'select', 'radio', 
            'date', 'datetime', 'time', 'color', 'hidden' => 'string',
            default => 'string',
        };
        
        $nullable = ($field['required'] ?? false) ? '' : '?';
        $default = $this->getDefaultValue($field);
        
        // For nullable fields, use null as default instead of empty string
        if (!$field['required'] && $field['type'] !== 'checkbox' && $field['type'] !== 'number' 
            && $field['type'] !== 'range' && $field['type'] !== 'file' && $field['type'] !== 'image' 
            && !isset($field['default'])) {
            $default = 'null';
        }
        
        // File/image uploads should be nullable by default
        if (in_array($field['type'], ['file', 'image']) && !isset($field['default'])) {
            $default = 'null';
        }
        
        return "    public {$nullable}{$type} \${$field['name']} = {$default};";
    }

    protected function getDefaultValue(array $field): string
    {
        if (isset($field['default'])) {
            if ($field['type'] === 'checkbox') {
                return $field['default'] ? 'true' : 'false';
            } elseif ($field['type'] === 'number') {
                return (string)$field['default'];
            } else {
                return "'{$field['default']}'";
            }
        }
        
        return match($field['type']) {
            'checkbox' => 'true',
            'number', 'range' => '0.0',
            'color' => "'#000000'",
            'file', 'image' => 'null',
            'textarea', 'text', 'email', 'password', 'url', 'tel', 'select', 'radio', 
            'date', 'datetime', 'time', 'hidden' => "''",
            default => "''",
        };
    }

    protected function createView(string $name, string $lowerName, string $lowerPlural, string $studlyPlural): void
    {
        $path = resource_path("views/admin/{$name}/views/management.blade.php");
        $dir = dirname($path);

        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        if (File::exists($path)) {
            $this->warn("View already exists: {$path}");
            return;
        }

        $stub = File::get(__DIR__ . '/stubs/view.stub');
        
        // Generate form fields HTML
        $formFields = [];
        $rowFields = [];
        $fieldCount = 0;
        
        foreach ($this->fields as $field) {
            if ($field['name'] === 'status') {
                continue; // Status will be added at the end
            }
            
            $fieldHtml = $this->generateFieldHtml($field);
            
            // Group fields in rows (3 per row for most, full width for textarea)
            if ($field['type'] === 'textarea') {
                if (!empty($rowFields)) {
                    $formFields[] = $this->wrapFieldsInRow($rowFields);
                    $rowFields = [];
                }
                $formFields[] = $fieldHtml;
            } else {
                $rowFields[] = $fieldHtml;
                $fieldCount++;
                
                if (count($rowFields) >= 3 || $fieldCount >= count($this->fields) - 1) {
                    $formFields[] = $this->wrapFieldsInRow($rowFields);
                    $rowFields = [];
                }
            }
        }
        
        // Add status field at the end
        $statusField = array_filter($this->fields, fn($f) => $f['name'] === 'status');
        if (!empty($statusField)) {
            $formFields[] = $this->generateFieldHtml(reset($statusField));
        }
        
        $formFieldsString = implode("\n\n", $formFields);
        
        $stub = str_replace(
            ['{{Name}}', '{{lowerName}}', '{{lowerPlural}}', '{{studlyPlural}}', '{{formFields}}'],
            [$name, $lowerName, $lowerPlural, $studlyPlural, $formFieldsString],
            $stub
        );

        File::put($path, $stub);
        $this->info("✅ Created View: {$path}");
    }

    protected function generateFieldHtml(array $field): string
    {
        $required = ($field['required'] ?? false) ? 'required' : '';
        $requiredStar = ($field['required'] ?? false) ? ' *' : '';
        $icon = $this->getFieldIcon($field['type']);
        $iconColor = $this->getFieldIconColor($field['type']);
        
        return match($field['type']) {
            'text', 'email', 'password', 'url', 'tel', 'number', 'date', 'datetime', 'time', 'color', 'range', 'hidden' => 
                $this->generateInputField($field, $required, $requiredStar, $icon, $iconColor),
            'textarea' => $this->generateTextareaField($field, $required, $requiredStar, $icon, $iconColor),
            'select' => $this->generateSelectField($field, $required, $requiredStar, $icon, $iconColor),
            'radio' => $this->generateRadioField($field, $required, $requiredStar, $icon, $iconColor),
            'checkbox' => $this->generateCheckboxField($field, $required, $requiredStar, $icon, $iconColor),
            'file', 'image' => $this->generateFileField($field, $required, $requiredStar, $icon, $iconColor),
            default => $this->generateInputField($field, $required, $requiredStar, $icon, $iconColor),
        };
    }

    protected function generateInputField(array $field, string $required, string $requiredStar, string $icon, string $iconColor): string
    {
        $inputType = match($field['type']) {
            'email' => 'email',
            'password' => 'password',
            'url' => 'url',
            'tel' => 'tel',
            'number', 'range' => 'number',
            'date' => 'text',
            'datetime' => 'text',
            'time' => 'time',
            'color' => 'color',
            'hidden' => 'hidden',
            default => 'text',
        };
        
        $step = '';
        $min = '';
        $max = '';
        $extraAttrs = '';
        $datePickerClass = '';
        $datePickerAttrs = '';
        
        if ($field['type'] === 'number') {
            $step = ' step="0.01"';
            $min = ' min="0"';
        } elseif ($field['type'] === 'range') {
            $min = ' min="' . ($field['min'] ?? '0') . '"';
            $max = ' max="' . ($field['max'] ?? '100') . '"';
            $step = ' step="' . ($field['step'] ?? '1') . '"';
        } elseif ($field['type'] === 'hidden') {
            $extraAttrs = ' style="display: none;"';
        } elseif ($field['type'] === 'date') {
            $datePickerClass = ' flatpickr-date';
            $datePickerAttrs = ' data-flatpickr-type="date"';
        } elseif ($field['type'] === 'datetime') {
            $datePickerClass = ' flatpickr-datetime';
            $datePickerAttrs = ' data-flatpickr-type="datetime"';
        }
        
        $labelHtml = $field['type'] !== 'hidden' 
            ? "<label for=\"{$field['name']}\" class=\"form-label {$required}\">
                    {$field['label']}" . (($field['required'] ?? false) ? ' <span class=\"text-danger\">*</span>' : '') . "
                </label>"
            : '';
        
        $colClass = $field['type'] === 'hidden' ? '' : 'col-md-4 mb-5';
        $wrapperStart = $field['type'] === 'hidden' ? '' : "<div class=\"{$colClass}\">";
        $wrapperEnd = $field['type'] === 'hidden' ? '' : '</div>';
        
        $inputWrapper = '';
        $inputWrapperEnd = '';
        $placeholder = "Enter {$field['label']}";
        if ($field['type'] === 'date') {
            $inputWrapper = '<div class="position-relative">';
            $inputWrapperEnd = '<i class="fa-solid fa-calendar position-absolute end-0 top-50 translate-middle-y me-3" style="color: #6b7280; pointer-events: none; cursor: pointer;" onclick="var el = document.getElementById(\'' . $field['name'] . '\'); if(el && el._flatpickr) el._flatpickr.open(); else el.focus();"></i></div>';
            $placeholder = 'dd/mm/yyyy';
        } elseif ($field['type'] === 'datetime') {
            $inputWrapper = '<div class="position-relative">';
            $inputWrapperEnd = '<i class="fa-solid fa-calendar position-absolute end-0 top-50 translate-middle-y me-3" style="color: #6b7280; pointer-events: none; cursor: pointer;" onclick="var el = document.getElementById(\'' . $field['name'] . '\'); if(el && el._flatpickr) el._flatpickr.open(); else el.focus();"></i></div>';
            $placeholder = 'dd/mm/yyyy HH:mm';
        }
        
        return <<<HTML
            {$wrapperStart}
                {$labelHtml}
                {$inputWrapper}
                <input type="{$inputType}" 
                       id="{$field['name']}"
                       wire:model="{$field['name']}"
                       class="form-control form-control-solid{$datePickerClass} @error('{$field['name']}') is-invalid @enderror"
                       placeholder="{$placeholder}"{$step}{$min}{$max}{$datePickerAttrs}{$extraAttrs}/>
                {$inputWrapperEnd}
                @error('{$field['name']}') 
                    <div class="text-danger mt-1">{{ \$message }}</div> 
                @enderror
            {$wrapperEnd}
HTML;
    }

    protected function generateTextareaField(array $field, string $required, string $requiredStar, string $icon, string $iconColor): string
    {
        $star = ($field['required'] ?? false) ? ' <span class="text-danger">*</span>' : '';
        return <<<HTML
        <div class="mb-5">
            <label for="{$field['name']}" class="form-label {$required}">
                {$field['label']}{$star}
            </label>
            <textarea id="{$field['name']}"
                      wire:model="{$field['name']}"
                      rows="4"
                      class="form-control form-control-solid @error('{$field['name']}') is-invalid @enderror"
                      placeholder="Enter {$field['label']}"></textarea>
            @error('{$field['name']}') 
                <div class="text-danger mt-1">{{ \$message }}</div> 
            @enderror
        </div>
HTML;
    }

    protected function generateSelectField(array $field, string $required, string $requiredStar, string $icon, string $iconColor): string
    {
        $options = '';
        $options .= "<option value=\"\">Select {$field['label']}</option>\n";
        
        // Handle foreign key relationships
        if (isset($field['foreign_key']) && $field['foreign_key']) {
            $relatedModel = $field['related_model'] ?? '';
            $displayField = $field['related_display'] ?? 'name';
            $options .= "@foreach(\\App\\Models\\{$relatedModel}::all() as \$item)\n";
            $options .= "                    <option value=\"{{\$item->id}}\">{{\$item->{$displayField}}}</option>\n";
            $options .= "                @endforeach\n";
        } else {
            foreach ($field['options'] ?? [] as $value => $label) {
                $options .= "                    <option value=\"{$value}\">{$label}</option>\n";
            }
        }
        
        $star = ($field['required'] ?? false) ? ' <span class="text-danger">*</span>' : '';
        return <<<HTML
            <div class="col-md-4 mb-5">
                <label for="{$field['name']}" class="form-label {$required}">
                    {$field['label']}{$star}
                </label>
                <select id="{$field['name']}"
                        wire:model="{$field['name']}"
                        class="form-select form-select-solid select2-field @error('{$field['name']}') is-invalid @enderror"
                        data-select2-field="{$field['name']}">
                    {$options}                </select>
                @error('{$field['name']}') 
                    <div class="text-danger mt-1">{{ \$message }}</div> 
                @enderror
            </div>
HTML;
    }

    protected function generateRadioField(array $field, string $required, string $requiredStar, string $icon, string $iconColor): string
    {
        $radios = '';
        foreach ($field['options'] ?? [] as $value => $label) {
            $radios .= <<<HTML
                    <div class="form-check form-check-custom form-check-solid">
                        <input class="form-check-input" 
                               type="radio" 
                               id="{$field['name']}_{$value}"
                               wire:model="{$field['name']}"
                               value="{$value}"/>
                        <label class="form-check-label" for="{$field['name']}_{$value}">
                            {$label}
                        </label>
                    </div>
HTML;
        }
        
        $star = ($field['required'] ?? false) ? ' <span class="text-danger">*</span>' : '';
        return <<<HTML
            <div class="col-md-4 mb-5">
                <label class="form-label {$required}">
                    {$field['label']}{$star}
                </label>
                <div class="d-flex gap-5">
                    {$radios}
                </div>
                @error('{$field['name']}') 
                    <div class="text-danger mt-1">{{ \$message }}</div> 
                @enderror
            </div>
HTML;
    }

    protected function generateCheckboxField(array $field, string $required, string $requiredStar, string $icon, string $iconColor): string
    {
        return <<<HTML
        <div class="mb-5">
            <label class="form-label">Status</label>
            <div class="form-check form-switch form-check-custom form-check-solid form-check-success">
                <input class="form-check-input" 
                       type="checkbox" 
                       id="{$field['name']}"
                       wire:model="{$field['name']}"
                       style="cursor: pointer;"/>
                <label class="form-check-label fw-semibold" for="{$field['name']}">
                    <span class="text-gray-700">Status:</span>
                    <span class="ms-2" x-text="\$wire.{$field['name']} ? 'Active' : 'Inactive'" 
                          :class="\$wire.{$field['name']} ? 'text-success' : 'text-danger'">
                        {{ \${$field['name']} ? 'Active' : 'Inactive' }}
                    </span>
                </label>
            </div>
            @error('{$field['name']}') 
                <div class="text-danger mt-1">{{ \$message }}</div> 
            @enderror
        </div>
HTML;
    }

    protected function wrapFieldsInRow(array $fields): string
    {
        return "<div class=\"row\">\n" . implode("\n", $fields) . "\n        </div>";
    }

    protected function generateFileField(array $field, string $required, string $requiredStar, string $icon, string $iconColor): string
    {
        $accept = $field['accept'] ?? ($field['type'] === 'image' ? 'image/*' : '*');
        $multiple = ($field['multiple'] ?? false) ? ' multiple' : '';
        $multipleText = ($field['multiple'] ?? false) ? 's' : '';
        $maxSize = $field['max_size'] ?? '2048';
        
        $star = ($field['required'] ?? false) ? ' <span class="text-danger">*</span>' : '';
        return <<<HTML
            <div class="col-md-4 mb-5">
                <label for="{$field['name']}" class="form-label {$required}">
                    {$field['label']}{$star}
                </label>
                <input type="file" 
                       id="{$field['name']}"
                       wire:model="{$field['name']}"
                       accept="{$accept}"
                       class="form-control form-control-solid @error('{$field['name']}') is-invalid @enderror"{$multiple}/>
                @if(\${$field['name']})
                    <div class="mt-2">
                        <small class="text-muted">File{$multipleText} selected: {{\${$field['name']}->getClientOriginalName() ?? 'N/A'}}</small>
                    </div>
                @endif
                @error('{$field['name']}') 
                    <div class="text-danger mt-1">{{ \$message }}</div> 
                @enderror
                <p class="text-muted mt-1 mb-0 small">Max size: {$maxSize} KB</p>
            </div>
HTML;
    }

    protected function getFieldIcon(string $type): string
    {
        return match($type) {
            'text' => 'fa-solid fa-tag',
            'email' => 'fa-solid fa-envelope',
            'password' => 'fa-solid fa-lock',
            'url' => 'fa-solid fa-link',
            'tel' => 'fa-solid fa-phone',
            'number', 'range' => 'fa-solid fa-hashtag',
            'textarea' => 'fa-solid fa-align-left',
            'select' => 'fa-solid fa-list',
            'radio' => 'fa-solid fa-circle-dot',
            'checkbox' => 'fa-solid fa-check-square',
            'date' => 'fa-solid fa-calendar',
            'datetime' => 'fa-solid fa-calendar-days',
            'time' => 'fa-solid fa-clock',
            'file' => 'fa-solid fa-file',
            'image' => 'fa-solid fa-image',
            'color' => 'fa-solid fa-palette',
            'hidden' => 'fa-solid fa-eye-slash',
            default => 'fa-solid fa-tag',
        };
    }

    protected function getFieldIconColor(string $type): string
    {
        return match($type) {
            'text' => 'text-primary',
            'email' => 'text-info',
            'password' => 'text-danger',
            'url' => 'text-primary',
            'tel' => 'text-success',
            'number', 'range' => 'text-success',
            'textarea' => 'text-warning',
            'select' => 'text-danger',
            'radio' => 'text-primary',
            'checkbox' => 'text-success',
            'date', 'datetime', 'time' => 'text-info',
            'file' => 'text-warning',
            'image' => 'text-primary',
            'color' => 'text-danger',
            'hidden' => 'text-muted',
            default => 'text-primary',
        };
    }

    protected function addModuleToSeeder(string $studlyName, string $lowerPlural): void
    {
        $seederPath = database_path('seeders/ModuleSeeder.php');
        
        if (!File::exists($seederPath)) {
            $this->warn("ModuleSeeder.php not found. Please add the module manually.");
            return;
        }

        $content = File::get($seederPath);
        
        // Check if module already exists
        $uniqueName = "admin.{$lowerPlural}";
        if (strpos($content, "'unique_name' => '{$uniqueName}'") !== false) {
            $this->warn("Module '{$uniqueName}' already exists in ModuleSeeder.php");
            return;
        }

        // Find the last order number
        preg_match_all("/'order' => (\d+),/", $content, $matches);
        $lastOrder = !empty($matches[1]) ? max(array_map('intval', $matches[1])) : 0;
        $nextOrder = $lastOrder + 1;

        // Generate module name (convert studly to readable format)
        $moduleName = str_replace('_', ' ', Str::title(Str::snake($studlyName))) . ' Management';
        
        // Default icon (can be customized)
        $icon = '<i class="fa-solid fa-box"></i>';

        // Generate the module entry
        $moduleEntry = "\n        // {$moduleName} Module\n        Module::firstOrCreate(\n            ['unique_name' => '{$uniqueName}'],\n            [\n                'name' => '{$moduleName}',\n                'icon' => '{$icon}',\n                'index_route' => 'admin.{$lowerPlural}.index',\n                'sub_routes' => ['admin.{$lowerPlural}.index'],\n                'order' => {$nextOrder},\n                'is_active' => true,\n            ]\n        );";

        // Find the position to insert (before the closing of run() method, before the info message)
        // Look for the info message line - handle both single and double quotes
        $lines = explode("\n", $content);
        $insertLineIndex = null;
        
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            if (preg_match('/\$this->command->info\([\'"]Modules seeded successfully![\'"]\);/', $lines[$i])) {
                $insertLineIndex = $i;
                break;
            }
        }
        
        if ($insertLineIndex === null) {
            // If info message not found, try to find the last closing brace
            for ($i = count($lines) - 1; $i >= 0; $i--) {
                if (trim($lines[$i]) === '}') {
                    $insertLineIndex = $i;
                    break;
                }
            }
        }
        
        if ($insertLineIndex === null) {
            $this->warn("Could not find insertion point in ModuleSeeder.php. Please add the module manually.");
            return;
        }
        
        // Calculate the position in the original content
        $insertPosition = 0;
        for ($i = 0; $i < $insertLineIndex; $i++) {
            $insertPosition += strlen($lines[$i]) + 1; // +1 for newline
        }
        
        // Ensure we have a newline before the info message
        if ($insertPosition > 0 && $content[$insertPosition - 1] !== "\n") {
            $moduleEntry = "\n" . $moduleEntry;
        }

        // Insert the module entry
        $newContent = substr_replace($content, $moduleEntry, $insertPosition, 0);
        
        File::put($seederPath, $newContent);
        $this->info("✅ Added module entry to ModuleSeeder.php (order: {$nextOrder})");
    }

    protected function createViewComponent(string $name, string $lowerName, string $lowerPlural, string $studlyPlural): void
    {
        $path = app_path("Src/Admin/Modules/{$name}/{$name}View.php");
        $dir = dirname($path);

        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        if (File::exists($path)) {
            $this->warn("View Component already exists: {$path}");
            return;
        }

        $stub = File::get(__DIR__ . '/stubs/viewcomponent.stub');

        // Find foreign key relationships for eager loading
        $withRelations = [];
        foreach ($this->fields as $field) {
            if (isset($field['foreign_key']) && $field['foreign_key'] && isset($field['related_model'])) {
                $relatedModel = $field['related_model'] ?? '';
                $relationName = Str::camel(Str::singular(Str::snake($relatedModel)));
                $withRelations[] = "'{$relationName}'";
            }
        }
        $relationsString = !empty($withRelations) ? implode(', ', array_unique($withRelations)) : '';

        // Get icon for the module
        $icon = $this->getModuleIcon($name);

        $stub = str_replace(
            ['{{Name}}', '{{lowerName}}', '{{lowerPlural}}', '{{relations}}', '{{icon}}'],
            [$name, $lowerName, $lowerPlural, $relationsString, $icon],
            $stub
        );

        File::put($path, $stub);
        $this->info("✅ Created View Component: {$path}");
    }

    protected function createViewPage(string $name, string $lowerName, string $lowerPlural, string $studlyPlural): void
    {
        $path = resource_path("views/admin/{$name}/views/view.blade.php");
        $dir = dirname($path);

        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        if (File::exists($path)) {
            $this->warn("View Page already exists: {$path}");
            return;
        }

        $stub = File::get(__DIR__ . '/stubs/viewpage.stub');

        // Generate view fields
        $viewFields = [];
        foreach ($this->fields as $field) {
            if ($field['type'] === 'hidden' || $field['type'] === 'password') {
                continue; // Skip hidden and password fields
            }

            $fieldHtml = $this->generateViewFieldHtml($field, $lowerName);
            $viewFields[] = $fieldHtml;
        }

        $viewFieldsString = implode("\n", $viewFields);

        // Get icon for the module
        $icon = $this->getModuleIcon($name);

        $stub = str_replace(
            ['{{Name}}', '{{lowerName}}', '{{lowerPlural}}', '{{viewFields}}', '{{icon}}'],
            [$name, $lowerName, $lowerPlural, $viewFieldsString, $icon],
            $stub
        );

        File::put($path, $stub);
        $this->info("✅ Created View Page: {$path}");
    }

    protected function generateViewFieldHtml(array $field, string $modelVar): string
    {
        $fieldName = $field['name'];
        $label = $field['label'];
        $fieldType = $field['type'];

        // Special handling for first field (usually name) - use ":-" instead of ":"
        $colon = ($fieldName === 'name' || $fieldName === 'title' || $fieldName === 'code') ? ':-' : ':';

        if ($fieldType === 'checkbox') {
            return <<<HTML
        <div class="mb-4" style="line-height: 2;">
            <span class="text-gray-600 fw-semibold fs-6">{$label}:</span>
            <span class="ms-2">
                @if(\${$modelVar}->{$fieldName})
                    <span class="badge badge-light-success">Active</span>
                @else
                    <span class="badge badge-light-danger">Inactive</span>
                @endif
            </span>
        </div>
HTML;
        } elseif ($fieldType === 'date') {
            return <<<HTML
        <div class="mb-4" style="line-height: 2;">
            <span class="text-gray-600 fw-semibold fs-6">{$label}:</span>
            <span class="text-gray-800 fw-bold fs-5 ms-2">
                @if(\${$modelVar}->{$fieldName})
                    {{ \${$modelVar}->{$fieldName}->format('d-m-Y') }}
                @else
                    N/A
                @endif
            </span>
        </div>
HTML;
        } elseif ($fieldType === 'datetime') {
            return <<<HTML
        <div class="mb-4" style="line-height: 2;">
            <span class="text-gray-600 fw-semibold fs-6">{$label}:</span>
            <span class="text-gray-800 fw-bold fs-5 ms-2">
                @if(\${$modelVar}->{$fieldName})
                    {{ \${$modelVar}->{$fieldName}->format('d-m-Y H:i') }}
                @else
                    N/A
                @endif
            </span>
        </div>
HTML;
        } elseif ($fieldType === 'select' && isset($field['foreign_key']) && $field['foreign_key']) {
            $relatedModel = $field['related_model'] ?? '';
            $displayField = $field['related_display'] ?? 'name';
            $relationName = Str::camel(Str::singular(Str::snake($relatedModel)));
            return <<<HTML
        @if(\${$modelVar}->{$relationName})
        <div class="mb-4" style="line-height: 2;">
            <span class="text-gray-600 fw-semibold fs-6">{$label}:</span>
            <span class="text-gray-800 fw-bold fs-5 ms-2">{{ \${$modelVar}->{$relationName}->{$displayField} ?? 'N/A' }}</span>
        </div>
        @endif
HTML;
        } elseif (in_array($fieldType, ['select', 'radio'])) {
            return <<<HTML
        <div class="mb-4" style="line-height: 2;">
            <span class="text-gray-600 fw-semibold fs-6">{$label}:</span>
            <span class="text-gray-800 fw-bold fs-5 ms-2">
                @if(\${$modelVar}->{$fieldName})
                    {{ ucfirst((string) \${$modelVar}->{$fieldName}) }}
                @else
                    N/A
                @endif
            </span>
        </div>
HTML;
        } elseif ($fieldType === 'image' || $fieldType === 'file') {
            return <<<HTML
        @if(\${$modelVar}->{$fieldName})
        <div class="mb-4" style="line-height: 2;">
            <span class="text-gray-600 fw-semibold fs-6">{$label}:</span>
            <span class="text-gray-800 fw-bold fs-5 ms-2">
                @if($fieldType === 'image')
                    <img src="{{ \Illuminate\Support\Facades\Storage::url(\${$modelVar}->{$fieldName}) }}" alt="{$label}" style="max-width: 200px; max-height: 200px;" class="img-thumbnail">
                @else
                    <a href="{{ \Illuminate\Support\Facades\Storage::url(\${$modelVar}->{$fieldName}) }}" target="_blank" class="text-primary">View File</a>
                @endif
            </span>
        </div>
        @endif
HTML;
        } else {
            return <<<HTML
        <div class="mb-4" style="line-height: 2;">
            <span class="text-gray-600 fw-semibold fs-6">{$label}{$colon}</span>
            <span class="text-gray-800 fw-bold fs-5 ms-2">{{ \${$modelVar}->{$fieldName} ?? 'N/A' }}</span>
        </div>
HTML;
        }
    }

    protected function getModuleIcon(string $name): string
    {
        $iconMap = [
            'Site' => 'building',
            'Product' => 'box',
            'Category' => 'tags',
            'Unit' => 'ruler',
            'Supplier' => 'truck',
            'Stock' => 'warehouse',
            'Order' => 'file-invoice',
            'Delivery' => 'truck-fast',
            'User' => 'user',
            'Role' => 'user-shield',
            'Permission' => 'key',
        ];

        $lowerName = Str::lower($name);
        foreach ($iconMap as $key => $icon) {
            if (Str::contains($lowerName, Str::lower($key))) {
                return $icon;
            }
        }

        return 'box'; // Default icon
    }
}

