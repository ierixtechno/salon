<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\NotificationTemplate;

/**
 * Simple `{{key}}` token substitution — deliberately not a templating
 * engine (no conditionals/loops/raw HTML), so a tenant-editable template
 * can never execute code (CLAUDE.md §30). Unknown tokens are left as-is
 * rather than silently blanked, so a typo'd placeholder is visible to
 * whoever reviews the sent message instead of disappearing.
 */
class RenderNotificationTemplate
{
    /**
     * @param  array<string, string>  $context
     * @return array{subject: ?string, body: string}
     */
    public function execute(NotificationTemplate $template, array $context): array
    {
        $replace = function (?string $text) use ($context): ?string {
            if ($text === null) {
                return null;
            }

            foreach ($context as $key => $value) {
                $text = str_replace('{{'.$key.'}}', $value, $text);
            }

            return $text;
        };

        return [
            'subject' => $replace($template->subject),
            'body' => $replace($template->body) ?? '',
        ];
    }
}
