# AZModule

AZModule is a Laravel package designed to streamline the process of generating new modules in your application based on a YAML (.yml) file. This tool allows you to define your module's database structure, forms, and API endpoints with a simple configuration file. The package is fully integrated with Laravel Breeze, Livewire, Spatie Laravel-Permission, and TALLStackUI, offering a complete solution for rapid module development.

## Features

- **Laravel:** [Laravel Documentation](https://laravel.com/docs)
- **Laravel Breeze:** [Laravel Breeze Documentation](https://laravel.com/docs/breeze)
- **Livewire:** [Livewire Documentation](https://laravel-livewire.com/docs)
- **Spatie Laravel-Permission:** [Spatie Laravel-Permission Documentation](https://spatie.be/docs/laravel-permission)
- **TALLStackUI:** [TALLStackUI Documentation](https://tallstack.dev/)

## Installation

**Install package:**

   ```bash
   composer require azcore/azmodule
   ```

## Example Configuration

Create a directory on your root project called `modules` and create a file .yml.
Here's an example of how to define a file called `category.yml`:

```yaml
name: Category

migrations:
  -
    table: categories
    fields:
      id: integer
      name: string
      active: boolean|default:true
      timestamp: true
      soft_deletes: true

index:
  search:
    name: like
  columns:
    name: Nome
    active: Ativo
    actions: Ações|sortable:false

form:
  fields:
    name: Nome|input|required
    active: Ativo|toggle|default:true

api:
  label: name
  value: id
```

## Usage

After creating your YAML configuration file, you can generate the module using the following Artisan command:

```bash
php artisan az:module category
```

This command will scaffold all the necessary files and configurations for the "Category" module, including migrations, views, controllers, and API resources.
