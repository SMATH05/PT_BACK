Route::resource('tasks', TaskController::class);
Route::get('tasks-by-chef-de-projet/{chefDeProjetId}', [TaskController::class, 'tasksByChefDeProjet']);
Route::get('tasks-by-status/{status}', [TaskController::class, 'tasksByStatus']);
Route::get('tasks-by-chef-de-projet-and-status/{chefDeProjetId}/{status}', [TaskController::class, 'tasksByChefDeProjetAndStatus']);
Route::put('tasks/{id}/status', [TaskController::class, 'updateStatus']);



