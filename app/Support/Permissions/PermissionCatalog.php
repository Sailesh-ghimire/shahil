<?php

namespace App\Support\Permissions;

/**
 * The global list of permissions (shared by every workspace) and the default roles
 * each new workspace starts with. Names follow `module.action` or `module.action.scope`.
 */
class PermissionCatalog
{
    public const OWNER = 'Owner';

    public const ADMIN = 'Admin';

    public const MANAGER = 'Manager';

    public const SALES_REP = 'Sales rep';

    public const VIEWER = 'Viewer';

    /**
     * @return array<string, list<string>>
     */
    public static function groups(): array
    {
        return [
            'Contacts & companies' => ['contacts.view', 'contacts.create', 'contacts.update.own', 'contacts.update.any', 'contacts.delete', 'contacts.import', 'contacts.export'],
            'Leads' => ['leads.view', 'leads.create', 'leads.update.own', 'leads.update.any', 'leads.convert', 'leads.delete'],
            'Deals' => ['deals.view', 'deals.create', 'deals.update.own', 'deals.update.any', 'deals.delete', 'deals.export'],
            'Tasks' => ['tasks.manage.own', 'tasks.manage.any'],
            'Inbox' => ['inbox.view', 'inbox.reply.assigned', 'inbox.reply.any', 'inbox.assign'],
            'Catalog' => ['products.manage'],
            'Reports' => ['reports.view.own', 'reports.view.team'],
            'Settings' => ['members.manage', 'roles.manage', 'channels.manage', 'pipelines.manage', 'audit.view', 'workspace.manage'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_merge(...array_values(static::groups()));
    }

    public static function label(string $permission): string
    {
        [$module, $rest] = array_pad(explode('.', $permission, 2), 2, '');

        return match ($module) {
            'audit' => 'View audit log',
            'workspace' => 'Workspace settings',
            'members', 'roles', 'channels', 'pipelines' => 'Manage '.$module,
            'products' => 'Manage products',
            default => match ($rest) {
                'view' => 'View',
                'create' => 'Create',
                'update.own' => 'Edit own',
                'update.any' => 'Edit any',
                'delete' => 'Delete',
                'import' => 'Import',
                'export' => 'Export',
                'convert' => 'Convert to deal',
                'manage.own' => 'Manage own',
                'manage.any' => 'Manage anyone’s',
                'reply.assigned' => 'Reply to assigned',
                'reply.any' => 'Reply to any',
                'assign' => 'Assign conversations',
                'view.own' => 'View own numbers',
                'view.team' => 'View whole team',
                default => $rest,
            },
        };
    }

    /**
     * @return array<string, array{description: string, permissions: list<string>}>
     */
    public static function defaultRoles(): array
    {
        $except = fn (string ...$excluded): array => array_values(array_diff(static::all(), $excluded));

        return [
            self::OWNER => [
                'description' => 'Full access. Can’t be edited.',
                'permissions' => static::all(),
            ],
            self::ADMIN => [
                'description' => 'Everything except workspace settings.',
                'permissions' => $except('workspace.manage'),
            ],
            self::MANAGER => [
                'description' => 'Runs the sales team and its data.',
                'permissions' => $except(
                    'workspace.manage', 'members.manage', 'roles.manage', 'channels.manage', 'audit.view',
                    'inbox.reply.assigned', 'reports.view.own', 'tasks.manage.own',
                    'contacts.update.own', 'leads.update.own', 'deals.update.own',
                ),
            ],
            self::SALES_REP => [
                'description' => 'Works own leads, deals and assigned chats.',
                'permissions' => [
                    'contacts.view', 'contacts.create', 'contacts.update.own',
                    'leads.view', 'leads.create', 'leads.update.own', 'leads.convert',
                    'deals.view', 'deals.create', 'deals.update.own',
                    'tasks.manage.own', 'inbox.view', 'inbox.reply.assigned', 'reports.view.own',
                ],
            ],
            self::VIEWER => [
                'description' => 'Read-only access and team reports.',
                'permissions' => ['contacts.view', 'leads.view', 'deals.view', 'inbox.view', 'reports.view.team'],
            ],
        ];
    }
}
