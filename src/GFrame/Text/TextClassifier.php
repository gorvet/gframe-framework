<?php

namespace GFrame\Text;

use Wamania\Snowball\StemmerFactory;

final class TextClassifier {

private array $tokenCache = [];
private $stemmer = null;
private array $stopWords = [];
private array $stopWordsCache = [];

/**
* Clasifica un texto según las palabras clave de los intents.
*
* @param string $text El texto a analizar.
* @param array $intents Arreglo de intents con palabras clave.
* @param string $language el lenguaje en que se va a analizar.
* @return string|null El ID del intent más acorde o null si no hay coincidencias.
* 
**/

public function intentsClassify(string $text, array $intents, string $language = 'spanish') {
    $this->stopWords = $this->getStopWords($language);
    $textTokens = $this->tokenize($text, $language);
    $normalizedText = $this->normalizeText($text);

    $bestMatchId = null;
    $bestMatchTokens = [];
    $maxScore = 0;
    $minScoreThreshold = 2;
    $bestCoverage = 0;
    $bestCoverageInverse = 0;
    $bestFirstMatchPos = PHP_INT_MAX;

    foreach ($intents as $intentId => $phrases) {
        $totalIntentScore = 0;
        $allIntentTokens = [];

        foreach ($phrases as $phrase) {
            if (!isset($this->tokenCache[$phrase])) {
                $this->tokenCache[$phrase] = $this->tokenize($phrase, $language);
            }
            $phraseTokens = $this->tokenCache[$phrase];
            $allIntentTokens = array_merge($allIntentTokens, $phraseTokens);

            $phraseScore = 0;

            // Coincidencias exactas
            $common = array_intersect($textTokens, $phraseTokens);
            $phraseScore += count($common) * 2;

            // Frase completa exacta
            if ($this->normalizeText((string)$phrase) === $normalizedText) {
                $phraseScore += 3;
            }

            // Levenshtein
            foreach ($textTokens as $textWord) {
                foreach ($phraseTokens as $kw) {
                    if (
                    strlen($textWord) >= 3 && strlen($kw) >= 3 &&
                    abs(strlen($textWord) - strlen($kw)) <= 2 &&
                    $this->levenshteinDistance($textWord, $kw) <= 1 &&
                    $textWord !== $kw
                    )
 {
                        $phraseScore += 1;
                    }
                }
            }

            $totalIntentScore += $phraseScore;
        }

        $allIntentTokens = array_values(array_unique($allIntentTokens));
        $matchedTokens = array_intersect($textTokens, $allIntentTokens);

        // Cobertura directa: cuánto del texto está en el intent
        $coverage = count($textTokens) > 0 ? count($matchedTokens) / count($textTokens) : 0;

        // Cobertura inversa: cuánto del intent está en el texto
        $coverageInverse = count($allIntentTokens) > 0 ? count($matchedTokens) / count($allIntentTokens) : 0;

        // Bonus por coberturas (escala suave)
        $coverageBonus = round(($coverage + $coverageInverse) * 2);
        $totalIntentScore += $coverageBonus;

        // Posición del primer match
        $matchPositions = [];
        foreach ($textTokens as $i => $tk) {
            if (in_array($tk, $allIntentTokens)) {
                $matchPositions[] = $i;
            }
        }
        $firstMatchPos = count($matchPositions) > 0 ? min($matchPositions) : PHP_INT_MAX;

        // Longitud total de tokens como última opción
        $currentLength = strlen(implode('', $allIntentTokens));
        $bestLength = strlen(implode('', $bestMatchTokens));

        // Comparación compuesta
        if (
            $totalIntentScore > $maxScore ||
            (
                $totalIntentScore === $maxScore &&
                ($coverage + $coverageInverse) > ($bestCoverage + $bestCoverageInverse)
            ) ||
            (
                $totalIntentScore === $maxScore &&
                ($coverage + $coverageInverse) === ($bestCoverage + $bestCoverageInverse) &&
                $firstMatchPos < $bestFirstMatchPos
            ) ||
            (
                $totalIntentScore === $maxScore &&
                ($coverage + $coverageInverse) === ($bestCoverage + $bestCoverageInverse) &&
                $firstMatchPos === $bestFirstMatchPos &&
                $currentLength > $bestLength
            )
        ) {
            $maxScore = $totalIntentScore;
            $bestMatchId = $intentId;
            $bestMatchTokens = $allIntentTokens;
            $bestCoverage = $coverage;
            $bestCoverageInverse = $coverageInverse;
            $bestFirstMatchPos = $firstMatchPos;
        }
    }

    return $maxScore < $minScoreThreshold ? -1 : $bestMatchId;
}

   

/**
* Tokeniza el texto dividiéndolo en palabras y elimina las stop words.
*
* @param string $text El texto a analizar.
* @param string $language el lenguaje a usar.
* @return array Un arreglo con las palabras tokenizadas sin stop words.
*/
private function tokenize(string $text,string $language='spanish'): array {


     $text = $this->normalizeText($text);
   
    // Tokenizar el texto en palabras
    $tokens = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
    //eliminar las stop words

    $tokens = array_filter($tokens, fn($t) => !in_array(mb_strtolower($t), $this->stopWords));

    // Stemming
    try {
        if (!$this->stemmer) {
            $this->stemmer = \Wamania\Snowball\StemmerFactory::create($language);
        }
        $stemmer = $this->stemmer;

        $tokens = array_map(fn($word) => $stemmer->stem($word), $tokens);
    } 
    catch (\Exception $e) {
         //fallback: dejar sin stemmear
    }

    $mgrams = $this->generateNgrams($tokens, 1);
    $bigrams = $this->generateNgrams($tokens, 2);
    $trigrams = $this->generateNgrams($tokens, 3);
    $tokens = array_merge($mgrams, $bigrams, $trigrams);

    // Eliminar tokens vacíos
    return array_values(array_filter($tokens, static fn($word): bool => $word !== ''));
}


private function getStopWords(string $language='spanish'): array {
    if (!isset($this->stopWordsCache[$language])) {
      $safeLanguage = preg_replace('/[^a-z_-]/i', '', $language) ?: 'spanish';
      $path = dirname(__DIR__, 3) . "/resources/stopwords/stopwords-$safeLanguage.json";
      $decoded = is_file($path) ? json_decode((string)file_get_contents($path), true) : [];
      $this->stopWordsCache[$language] = is_array($decoded) ? $decoded : [];
    }

    return $this->stopWordsCache[$language];
}



private function generateNgrams(array $tokens, int $n = 1): array {
    $ngrams = [];
    for ($i = 0; $i <= count($tokens) - $n; $i++) {
        $ngrams[] = implode(' ', array_slice($tokens, $i, $n));
    }
    //print_r($ngrams);
    //echo "<br>";
    return $ngrams;
}

/**
* Calcula la distancia de Levenshtein para manejar coincidencias parciales.
*
* @param string $word1 Primera palabra.
* @param string $word2 Segunda palabra.
* @return int La distancia de Levenshtein.
*/
private function levenshteinDistance(string $word1, string $word2): int{
    return levenshtein($word1, $word2);
}

private function normalizeText(string $text): string {
    $text = mb_strtolower($text); // minúsculas

    // Reemplazar tildes y diéresis
    $text = preg_replace('/[áàäâã]/u', 'a', $text);
    $text = preg_replace('/[éèëê]/u', 'e', $text);
    $text = preg_replace('/[íìïî]/u', 'i', $text);
    $text = preg_replace('/[óòöôõ]/u', 'o', $text);
    $text = preg_replace('/[úùüû]/u', 'u', $text);

    // Reemplazar ñ por n
    $text = str_replace('ñ', 'n', $text);

    // Reducir repeticiones de letras (más de 2 iguales seguidas)
    $text = preg_replace('/(\p{L})\1{2,}/u', '$1', $text); // hooooola → hola
    $text = preg_replace('/(\p{L})\1/u', '$1', $text); // opcional: si quieres reducir aa → a


    // Eliminar cualquier carácter que no sea letra, número o espacio
    $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);

    return $text;
}









}
