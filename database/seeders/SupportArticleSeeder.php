<?php

namespace Database\Seeders;

use App\Models\SupportArticle;
use Illuminate\Database\Seeder;

class SupportArticleSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            ['category' => 'Getting started', 'slug' => 'create-your-first-project', 'title' => 'Create your first project', 'body' => "Open Projects from your workspace navigation and choose New project. Add a clear name, delivery dates, and the teammates who need access.\n\nElara creates a practical default workflow that you can adjust from the project board."],
            ['category' => 'Tasks', 'slug' => 'organize-work-with-task-views', 'title' => 'Organize work with task views', 'body' => "List, Board, Calendar, Schedule, Dashboard, and Performance all read the same task records. Update a task in one view and the change appears everywhere else.\n\nUse priorities, assignees, dates, and categories to keep filters useful."],
            ['category' => 'Security', 'slug' => 'protect-account-with-two-factor', 'title' => 'Protect your account with two-factor authentication', 'body' => "Open Settings, then Security. Start setup, scan the QR code with a TOTP authenticator, and confirm one rotating code.\n\nStore your one-time recovery codes somewhere separate from your password."],
            ['category' => 'Getting started', 'slug' => 'manage-subscription-and-invoices', 'title' => 'Unlimited workspace access', 'body' => "Elara does not apply package quotas to workspace members, projects, application storage, integrations, or CSV and PDF exports.\n\nInfrastructure storage capacity and limits imposed directly by connected providers still apply."],
            ['category' => 'Integrations', 'slug' => 'connect-team-tools', 'title' => 'Connect Slack, Drive, GitHub, or Zoom', 'body' => "Workspace owners connect providers from Settings, then Integrations. Elara requests only the scopes needed for the action shown in each provider card.\n\nDisconnecting revokes provider access when supported and removes locally encrypted tokens."],
            ['category' => 'FAQ', 'slug' => 'are-files-private', 'title' => 'Are uploaded files private?', 'body' => 'Yes. Elara stores project files on a private disk and streams previews or downloads only after checking workspace, project, and file permissions.'],
            ['category' => 'FAQ', 'slug' => 'why-cant-i-edit-a-project', 'title' => 'Why can’t I edit a project?', 'body' => 'Your workspace and project roles determine what you can change. Viewers are read-only, while owners, admins, and project managers receive broader permissions within their scope.'],
            ['category' => 'FAQ', 'slug' => 'how-do-plan-limits-work', 'title' => 'How do plan limits work?', 'body' => 'Elara checks limits on the server when inviting members, creating active projects, uploading files, connecting integrations, and exporting reports. Changing HTML in the browser cannot bypass them.'],
        ];

        foreach ($articles as $article) {
            SupportArticle::updateOrCreate(['slug' => $article['slug']], [...$article, 'is_published' => true]);
        }
    }
}
