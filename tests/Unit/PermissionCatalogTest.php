<?php

use App\Support\Permissions\PermissionCatalog;

it('has 34 unique permissions in module.action form', function () {
    $all = PermissionCatalog::all();

    expect($all)->toHaveCount(34)
        ->and(array_unique($all))->toHaveCount(34)
        ->and($all)->each->toMatch('/^[a-z]+\.[a-z]+(\.[a-z]+)?$/');
});

it('only grants permissions that exist', function () {
    foreach (PermissionCatalog::defaultRoles() as $role) {
        expect(array_diff($role['permissions'], PermissionCatalog::all()))->toBe([]);
    }
});

it('gives each default role what the plan says', function () {
    $roles = PermissionCatalog::defaultRoles();

    expect($roles[PermissionCatalog::OWNER]['permissions'])->toHaveCount(34)
        ->and($roles[PermissionCatalog::ADMIN]['permissions'])->not->toContain('workspace.manage')
        ->and($roles[PermissionCatalog::MANAGER]['permissions'])->toContain('deals.update.any', 'pipelines.manage', 'contacts.import')
        ->and($roles[PermissionCatalog::MANAGER]['permissions'])->not->toContain('members.manage', 'audit.view')
        ->and($roles[PermissionCatalog::SALES_REP]['permissions'])->toContain('deals.update.own', 'inbox.reply.assigned', 'reports.view.own')
        ->and($roles[PermissionCatalog::SALES_REP]['permissions'])->not->toContain('deals.update.any', 'contacts.delete', 'contacts.import')
        ->and($roles[PermissionCatalog::VIEWER]['permissions'])->toBe(['contacts.view', 'leads.view', 'deals.view', 'inbox.view', 'reports.view.team']);
});

it('labels permissions in plain words', function () {
    expect(PermissionCatalog::label('deals.update.own'))->toBe('Edit own')
        ->and(PermissionCatalog::label('audit.view'))->toBe('View audit log')
        ->and(PermissionCatalog::label('inbox.reply.assigned'))->toBe('Reply to assigned');
});
