## Installation

Add to dependencies

	"cartalyst/sentinel": "^2.0",
    "google/apiclient": "1.0.*@beta",

Add to providers

	Rjvim\Connect\ConnectServiceProvider::class,

Add to facades

	'Connect'   => Rjvim\Connect\ConnectFacade::class,

Run: `php artisan vendor:publish`

Add more columns to users table:

	$table->string('name')->nullable();
    $table->text('description')->nullable();
    $table->enum('gender', ['male', 'female', 'others'])->nullable();
    $table->date('birthday')->nullable();
    $table->text('photo')->nullable();

Extend User model with `Rjvim\Connect\Models\User`

Add routes:
	
	Connect::google();
