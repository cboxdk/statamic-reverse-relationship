<?php

use Cbox\ReverseRelationship\Http\Controllers\ReverseRelationshipController;

Route::get('reverse-relationship', [ReverseRelationshipController::class, 'index'])->name('reverse-relationship.index');
Route::post('reverse-relationship/sync', [ReverseRelationshipController::class, 'sync'])->name('reverse-relationship.sync');
Route::get('reverse-relationship/search', [ReverseRelationshipController::class, 'search'])->name('reverse-relationship.search');
Route::get('reverse-relationship/fields', [ReverseRelationshipController::class, 'fields'])->name('reverse-relationship.fields');
