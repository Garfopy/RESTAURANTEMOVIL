<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Config\Database;
use Amare\Api\Helpers\Response;

class SettingsController
{
    private const DEFAULT_PRIMARY = '#1A1A2E';
    private const DEFAULT_SECONDARY = '#E8A020';
    private const DEFAULT_BACKGROUND = '#F5F5F7';
    private const DEFAULT_BUTTON = '#1A1A2E';
    private const DEFAULT_BUTTON_TEXT = '#FFFFFF';

    /**
     * GET /settings/theme
     * Devuelve los colores globales que consume la app movil.
     */
    public function theme(): void
    {
        try {
            $settings = Database::query(
                "SELECT clave, valor
                 FROM global_settings
                 WHERE clave IN (
                    'color_primary',
                    'color_secondary',
                    'app_background_color',
                    'app_button_color',
                    'app_button_text_color'
                 )"
            );
        } catch (\Throwable) {
            $settings = [];
        }

        $values = [];
        foreach ($settings as $setting) {
            $values[$setting['clave']] = $setting['valor'];
        }

        $primary = $this->validHexColor($values['color_primary'] ?? null)
            ? strtoupper($values['color_primary'])
            : self::DEFAULT_PRIMARY;

        $secondary = $this->validHexColor($values['color_secondary'] ?? null)
            ? strtoupper($values['color_secondary'])
            : self::DEFAULT_SECONDARY;

        $background = $this->validHexColor($values['app_background_color'] ?? null)
            ? strtoupper($values['app_background_color'])
            : self::DEFAULT_BACKGROUND;

        $button = $this->validHexColor($values['app_button_color'] ?? null)
            ? strtoupper($values['app_button_color'])
            : $primary;

        $buttonText = $this->validHexColor($values['app_button_text_color'] ?? null)
            ? strtoupper($values['app_button_text_color'])
            : self::DEFAULT_BUTTON_TEXT;

        Response::success([
            'theme' => [
                'primary' => $primary,
                'secondary' => $secondary,
                'background' => $background,
                'button' => $button,
                'buttonText' => $buttonText,
            ],
        ]);
    }

    private function validHexColor(?string $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1;
    }
}
