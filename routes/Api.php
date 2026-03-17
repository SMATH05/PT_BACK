Route::resource('tasks', TaskController::class);
Route::get('tasks-by-chef-de-projet/{chefDeProjetId}', [TaskController::class, 'tasksByChefDeProjet']);