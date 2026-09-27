<?php
namespace Hostinger\AiTheme\Builder\Dto;

class ColorPaletteDto {
    public string $color1;
    public string $color2;
    public string $color3;
    public string $light;
    public string $dark;
    public string $grey;
    /** @var Gradient[] */
    public array $gradients;
    public string $page_background;
    public string $page_text;

    public function __construct( string $color1, string $color2, string $color3, string $light, string $dark, string $grey, array $gradients, string $page_background = '', string $page_text = '' ) {
        $this->color1          = $color1;
        $this->color2          = $color2;
        $this->color3          = $color3;
        $this->light           = $light;
        $this->dark            = $dark;
        $this->grey            = $grey;
        $this->gradients       = $gradients;
        $this->page_background = $page_background;
        $this->page_text       = $page_text;
    }

    public function get_color_1(): string {
        return $this->color1;
    }

    public function set_color_1( string $color1 ): void {
        $this->color1 = $color1;
    }

    public function get_color_2(): string {
        return $this->color2;
    }

    public function set_color_2( string $color2 ): void {
        $this->color2 = $color2;
    }

    public function get_color_3(): string {
        return $this->color3;
    }

    public function set_color_3( string $color3 ): void {
        $this->color3 = $color3;
    }

    public function get_light(): string {
        return $this->light;
    }

    public function set_light( string $light ): void {
        $this->light = $light;
    }

    public function get_dark(): string {
        return $this->dark;
    }

    public function set_dark( string $dark ): void {
        $this->dark = $dark;
    }

    public function get_grey(): string {
        return $this->grey;
    }

    public function set_grey( string $grey ): void {
        $this->grey = $grey;
    }

    public function get_page_background(): string {
        return $this->page_background;
    }

    public function get_page_text(): string {
        return $this->page_text;
    }

    public function get_gradients(): array {
        return $this->gradients;
    }

    public function get_main_gradient(): GradientDto {
        return !empty($this->gradients[0]) ? $this->gradients[0] : new GradientDto( 'abc', '#000');
    }

    public function set_gradients( array $gradients ): void {
        $this->gradients = $gradients;
    }

    public static function from_array( array $color_palette ): self {
        $gradients = array();
        if(!empty($color_palette['gradients'])) {
            foreach($color_palette['gradients'] as $gradient_id => $gradient) {
                $gradients[] = new GradientDto( $gradient_id, $gradient['gradient'] ?? '' );
            }
        }

        return new self(
            $color_palette['color1'] ?? '',
            $color_palette['color2'] ?? '',
            $color_palette['color3'] ?? '',
            $color_palette['light'] ?? '',
            $color_palette['dark'] ?? '',
            $color_palette['grey'] ?? '',
            $gradients,
            $color_palette['page_background'] ?? '',
            $color_palette['page_text'] ?? '',
        );
    }
}
