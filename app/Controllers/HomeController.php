<?php
namespace App\Controllers;

use App\Core\View;

final class HomeController
{
    public function index(): void
    {
        $projects = [
            ['name' => 'SDN 2 Cianting', 'image' => 'images/projects/SDN_2_CIANTING_01.png'],
            ['name' => 'SDN 2 Cibuntu', 'image' => 'images/projects/SDN_2_CIBUNTU_01.png'],
            ['name' => 'SDN 3 Panyindangan', 'image' => 'images/projects/SDN_3_PANYINDANGAN_01.png'],
            ['name' => 'SDN 2 Sukasari', 'image' => 'images/projects/SDN_2_SUKASARI_01.png'],
        ];
        View::render('public/compro_home', ['title' => 'CV BUANA SASTRA DAHANA | Konstruksi & Supply Material', 'projects' => $projects, 'page' => 'home'], 'layouts/compro');
    }

    public function legalitas(): void
    {
        View::render('public/legalitas', ['title' => 'Legalitas Kami | CV BUANA SASTRA DAHANA', 'page' => 'legalitas'], 'layouts/compro');
    }
}
