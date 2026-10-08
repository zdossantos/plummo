<?php

namespace Database\Seeders;

use App\Enums\ContentType;
use App\Models\Content;
use App\Models\Pack;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedQuiz();
        $this->seedDrawing();
        $this->seedPhrases();
    }

    private function seedQuiz(): void
    {
        $tag = Tag::firstOrCreate(['name' => 'Démo · Quiz']);
        $pack = Pack::firstOrCreate(['name' => 'Démo · Quiz général']);
        $pack->tags()->syncWithoutDetaching([$tag->id]);

        $questions = [
            ['Quelle planète est surnommée la planète rouge ?', ['Vénus', 'Mars', 'Jupiter', 'Mercure'], 1],
            ['Combien de côtés possède un hexagone ?', ['Quatre', 'Cinq', 'Six', 'Huit'], 2],
            ['Quel animal est la mascotte de Plummo ?', ['Un renard', 'Un plummo', 'Un dragon', 'Un lama'], 1],
            ['Dans quelle ville se trouve la tour Eiffel ?', ['Lyon', 'Marseille', 'Paris', 'Lille'], 2],
            ['Quel est le plus grand océan du monde ?', ['Atlantique', 'Indien', 'Arctique', 'Pacifique'], 3],
            ['Combien de minutes y a-t-il dans une heure ?', ['30', '45', '60', '90'], 2],
            ['Quelle couleur obtient-on en mélangeant du bleu et du jaune ?', ['Orange', 'Vert', 'Violet', 'Rose'], 1],
            ['Quel instrument possède généralement 88 touches ?', ['Le piano', 'La guitare', 'La flûte', 'Le violon'], 0],
            ['Quel mois vient juste après avril ?', ['Mars', 'Mai', 'Juin', 'Juillet'], 1],
            ['Quel gaz respirons-nous principalement dans l’air ?', ['Oxygène', 'Azote', 'Hélium', 'Hydrogène'], 1],
            ['Combien de jours compte une année bissextile ?', ['364', '365', '366', '367'], 2],
            ['Quel est le contraire de “rapide” ?', ['Lent', 'Fort', 'Grand', 'Tôt'], 0],
        ];

        foreach ($questions as [$question, $choices, $correct]) {
            $content = Content::firstOrCreate(
                ['type' => ContentType::Quiz, 'payload->question' => $question],
                ['payload' => ['question' => $question, 'choices' => $choices, 'correct' => $correct], 'published' => true],
            );
            $content->tags()->syncWithoutDetaching([$tag->id]);
        }
    }

    private function seedDrawing(): void
    {
        $tag = Tag::firstOrCreate(['name' => 'Démo · Dessin']);
        $pack = Pack::firstOrCreate(['name' => 'Démo · Dessin express']);
        $pack->tags()->syncWithoutDetaching([$tag->id]);

        foreach (['Chat', 'Maison', 'Soleil', 'Vélo', 'Pizza', 'Robot', 'Palmier', 'Guitare', 'Fusée', 'Château', 'Licorne', 'Avion', 'Glace', 'Ballon', 'Fantôme', 'Trèfle', 'Livre', 'Bateau', 'Panda', 'Couronne', 'Montagne', 'Parapluie', 'Étoile', 'Dinosaure', 'Cactus', 'Arc-en-ciel', 'Téléphone', 'Dragon', 'Tortue', 'Pop-corn'] as $word) {
            $content = Content::firstOrCreate(
                ['type' => ContentType::Drawing, 'payload->word' => $word],
                ['payload' => ['word' => $word], 'published' => true],
            );
            $content->tags()->syncWithoutDetaching([$tag->id]);
        }
    }

    private function seedPhrases(): void
    {
        $tag = Tag::firstOrCreate(['name' => 'Démo · Phrases']);
        $pack = Pack::firstOrCreate(['name' => 'Démo · Phrases décalées']);
        $pack->tags()->syncWithoutDetaching([$tag->id]);

        foreach (['Si mon animal pouvait parler, il dirait…', 'Le pire super-pouvoir serait…', 'À la maison, personne ne sait que je…', 'Le slogan officiel de notre groupe serait…', 'La vraie raison pour laquelle je suis en retard…', 'Mon invention la plus inutile serait…'] as $prompt) {
            $content = Content::firstOrCreate(
                ['type' => ContentType::Phrase, 'payload->prompt' => $prompt],
                ['payload' => ['prompt' => $prompt], 'published' => true],
            );
            $content->tags()->syncWithoutDetaching([$tag->id]);
        }
    }
}
