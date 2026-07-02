<?php
/**
 * Aether NLP Engine
 * 4-Step Computational Linguistics Pipeline for Commodities News Sentiment
 */

class NLPEngine {
    private array $stopwords = [
        'the', 'a', 'an', 'and', 'or', 'but', 'is', 'are', 'was', 'were',
        'be', 'been', 'being', 'in', 'on', 'at', 'to', 'for', 'with', 'by',
        'about', 'against', 'between', 'into', 'through', 'during', 'before',
        'after', 'above', 'below', 'from', 'up', 'down', 'out', 'off', 'over', 'under',
        'it', 'will', 'you', 'i', 'have', 'your'
    ];

    private array $negations = ['not', 'no', 'never', 'fails', 'failed', 'cannot', 'cant', 'avoid', 'without', 'despite', 'unlikely', 'ends', 'end'];

    private array $entities = [
        // Precious metals
        'gold'       => 1.6,  'xau'        => 1.6,  'bullion'    => 1.5,
        'silver'     => 1.5,  'xag'        => 1.5,  'platinum'   => 1.2,
        'palladium'  => 1.1,  'commodity'  => 1.1,  'commodities'=> 1.1,
        'comex'      => 1.3,  'metals'     => 1.3,
        // Macro
        'fed'        => 1.4,  'fomc'       => 1.4,  'powell'     => 1.3,
        'dxy'        => 1.2,  'ecb'        => 1.1,  'boj'        => 1.1,
        // Geopolitical
        'russia'     => 1.2,  'china'      => 1.1,  'opec'       => 1.1,
        // Crypto (correlated assets)
        'btc'        => 0.8,  'bitcoin'    => 0.8,
        'eth'        => 0.7,  'ethereum'   => 0.7,
        // Regulatory / Mining Context
        'sec'        => 1.0,  'cftc'       => 1.1,  'mining'     => 1.2,
        'miner'      => 1.2,  'miners'     => 1.2,  'rates'      => 1.4,
    ];

    private array $lexicon = [
        // Category 1: Price Action
        'surge'      => ['polarity' =>  1, 'weight' => 1.8],
        'rally'      => ['polarity' =>  1, 'weight' => 1.2],
        'breakout'   => ['polarity' =>  1, 'weight' => 1.5],
        'soars'      => ['polarity' =>  1, 'weight' => 1.8],
        'plunge'     => ['polarity' => -1, 'weight' => 1.8],
        'crash'      => ['polarity' => -1, 'weight' => 2.0],
        'drops'      => ['polarity' => -1, 'weight' => 1.0],
        'dumps'      => ['polarity' => -1, 'weight' => 1.5],
        'rebounds'   => ['polarity' =>  1, 'weight' => 1.2],
        'correction' => ['polarity' => -1, 'weight' => 1.0],
        'climbs'     => ['polarity' =>  1, 'weight' => 1.0],
        'rises'      => ['polarity' =>  1, 'weight' => 1.0],
        'rising'     => ['polarity' =>  1, 'weight' => 1.0],
        'falls'      => ['polarity' => -1, 'weight' => 1.0],
        'falling'    => ['polarity' => -1, 'weight' => 1.0],
        'good'       => ['polarity' =>  1, 'weight' => 1.0],
        'bad'        => ['polarity' => -1, 'weight' => 1.0],
        'collapses'  => ['polarity' => -1, 'weight' => 2.0],
        'spikes'     => ['polarity' =>  1, 'weight' => 1.4],
        'slips'      => ['polarity' => -1, 'weight' => 0.8],
        'stabilises' => ['polarity' =>  0, 'weight' => 0.5],
        'stabilizes' => ['polarity' =>  0, 'weight' => 0.5],
        
        // Category 2: Macro & Central Bank
        'inflation'  => ['polarity' =>  1, 'weight' => 1.2], // Usually bullish for gold
        'deflation'  => ['polarity' => -1, 'weight' => 1.0],
        'recession'  => ['polarity' => -1, 'weight' => 1.5], // Macro bearish
        'rate'       => ['polarity' => -1, 'weight' => 0.8], // Often refers to interest rates
        'hike'       => ['polarity' => -1, 'weight' => 1.2],
        'cut'        => ['polarity' =>  1, 'weight' => 1.2],
        'dovish'     => ['polarity' =>  1, 'weight' => 1.5],
        'hawkish'    => ['polarity' => -1, 'weight' => 1.5],
        'stimulus'   => ['polarity' =>  1, 'weight' => 1.5],
        'tightening' => ['polarity' => -1, 'weight' => 1.2],
        'easing'     => ['polarity' =>  1, 'weight' => 1.2],
        'yields'     => ['polarity' => -1, 'weight' => 1.0],
        'dollar'     => ['polarity' => -1, 'weight' => 1.0],
        'usd'        => ['polarity' => -1, 'weight' => 1.0],
        'debt'       => ['polarity' => -1, 'weight' => 1.0],
        'crisis'     => ['polarity' => -1, 'weight' => 1.8],
        
        // Category 3: Precious Metals Specific
        'demand'     => ['polarity' =>  1, 'weight' => 1.2],
        'supply'     => ['polarity' => -1, 'weight' => 0.8],
        'shortage'   => ['polarity' =>  1, 'weight' => 1.5],
        'stockpile'  => ['polarity' => -1, 'weight' => 1.0],
        'safe'       => ['polarity' =>  1, 'weight' => 1.0],
        'haven'      => ['polarity' =>  1, 'weight' => 1.2],
        'hedge'      => ['polarity' =>  1, 'weight' => 1.0],
        'bullion'    => ['polarity' =>  1, 'weight' => 0.8],
        
        // Category 4: Market Structure
        'bullish'    => ['polarity' =>  1, 'weight' => 1.6],
        'bearish'    => ['polarity' => -1, 'weight' => 1.6],
        'accumulation'=>['polarity' =>  1, 'weight' => 1.2],
        'liquidation'=> ['polarity' => -1, 'weight' => 1.4],
        'inflows'    => ['polarity' =>  1, 'weight' => 1.0],
        'outflows'   => ['polarity' => -1, 'weight' => 1.0],
        'ath'        => ['polarity' =>  1, 'weight' => 2.0],
        'oversold'   => ['polarity' =>  1, 'weight' => 1.0],
        'overbought' => ['polarity' => -1, 'weight' => 1.0],
        'support'    => ['polarity' =>  1, 'weight' => 0.8],
        'resistance' => ['polarity' => -1, 'weight' => 0.8],
        'trap'       => ['polarity' => -1, 'weight' => 1.0],
        'burden'     => ['polarity' => -1, 'weight' => 1.0],

        // Category 5: Geopolitical & Supply Shocks
        'war'        => ['polarity' =>  1, 'weight' => 1.5], // Safe haven demand
        'conflict'   => ['polarity' =>  1, 'weight' => 1.2],
        'sanction'   => ['polarity' =>  1, 'weight' => 1.2],
        'uncertainty'=> ['polarity' =>  1, 'weight' => 1.0],
        'volatility' => ['polarity' => -1, 'weight' => 0.8],
        'panic'      => ['polarity' => -1, 'weight' => 1.8],
        'fear'       => ['polarity' => -1, 'weight' => 1.5],
        'bars'       => ['polarity' =>  1, 'weight' => 1.4], // Supply constraint = bullish
        'bans'       => ['polarity' =>  1, 'weight' => 1.4],
        'ban'        => ['polarity' =>  1, 'weight' => 1.4],
        'restrict'   => ['polarity' =>  1, 'weight' => 1.2],
        'halts'      => ['polarity' =>  1, 'weight' => 1.2],
        'strike'     => ['polarity' =>  1, 'weight' => 1.0],

        // Category 6: Regulatory
        'approved'   => ['polarity' =>  1, 'weight' => 1.2],
        'rejected'   => ['polarity' => -1, 'weight' => 1.2],
        'investigation'=>['polarity'=> -1, 'weight' => 1.0],
        'etf'        => ['polarity' =>  1, 'weight' => 1.0],
        'institutional'=>['polarity'=>  1, 'weight' => 1.2],
        'confidence' => ['polarity' =>  1, 'weight' => 1.2],
        'optimism'   => ['polarity' =>  1, 'weight' => 1.4],
    ];

    private array $phrases = [
        'strong buy'       => ['polarity' =>  1, 'weight' => 1.8],
        'sell off'         => ['polarity' => -1, 'weight' => 1.8],
        'record high'      => ['polarity' =>  1, 'weight' => 2.0],
        'all time high'    => ['polarity' =>  1, 'weight' => 2.0],
        'safe haven'       => ['polarity' =>  1, 'weight' => 1.5],
        'rate cut'         => ['polarity' =>  1, 'weight' => 1.6],
        'rate hike'        => ['polarity' => -1, 'weight' => 1.6],
        'interest rate hike'=>['polarity' => -1, 'weight' => 1.8],
        'interest rate cut'=> ['polarity' =>  1, 'weight' => 1.8],
        'fear and greed'   => ['polarity' =>  1, 'weight' => 0.2],
        'risk off'         => ['polarity' => -1, 'weight' => 1.4],
        'risk on'          => ['polarity' =>  1, 'weight' => 1.4],
        'flight to safety' => ['polarity' =>  1, 'weight' => 1.6],
        'trade war'        => ['polarity' => -1, 'weight' => 1.2],
        'interest rate'    => ['polarity' => -1, 'weight' => 0.8],
    ];

    /**
     * Step 1: Tokenize and strip stopwords
     */
    private function tokenize(string $headline): array {
        // Decode HTML entities (e.g. &#8217; -> ’) so they can be properly stripped
        $headline = html_entity_decode($headline, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = strtolower($headline);
        // Strip all punctuation including standard and curly apostrophes/quotes
        $normalized = preg_replace('/[.,\/#!$%\^&\*;:{}=\-_`~()\'"’‘“”]/', '', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        
        $tokens = explode(' ', trim($normalized));
        $filteredTokens = [];
        
        foreach ($tokens as $token) {
            if (!empty($token) && !in_array($token, $this->stopwords)) {
                $filteredTokens[] = $token;
            }
        }
        return $filteredTokens;
    }

    /**
     * Main analysis method (Steps 2-4)
     */
    public function analyze(string $headline): array {
        if (empty(trim($headline))) {
            return ['score' => 0.0, 'confidence' => 'Low', 'tag' => 'Neutral'];
        }

        $tokens = $this->tokenize($headline);
        $numTokens = count($tokens);
        $consumed = array_fill(0, $numTokens, false);
        $totalRawScore = 0.0;
        $matchedTokenCount = 0;
        $matchedEntityCount = 0;
        $negationIndices = [];

        // Identify negations for Step 3, but DO NOT consume them yet so phrases can still use them if needed.
        foreach ($tokens as $i => $token) {
            if (in_array($token, $this->negations)) {
                $negationIndices[] = $i;
            }
        }

        // Helper to check negation window (Step 3)
        // If word is at position j, find nearest preceding negation at i
        // where j > i and j <= i + 4
        $getNegationMultiplier = function($j) use ($negationIndices) {
            foreach ($negationIndices as $i) {
                if ($j > $i && $j <= $i + 4) {
                    $distance = $j - $i;
                    return -1 * (1.0 - 0.15 * ($distance - 1));
                }
            }
            return 1.0;
        };

        // Step 2: Phrase Pass (Trigrams, then Bigrams)
        for ($len = 3; $len >= 2; $len--) {
            for ($i = 0; $i <= $numTokens - $len; $i++) {
                // Check if any part of the phrase is already consumed
                $isConsumed = false;
                for ($k = 0; $k < $len; $k++) {
                    if ($consumed[$i + $k]) {
                        $isConsumed = true;
                        break;
                    }
                }
                
                if (!$isConsumed) {
                    $phraseArray = array_slice($tokens, $i, $len);
                    $phraseStr = implode(' ', $phraseArray);
                    
                    if (isset($this->phrases[$phraseStr])) {
                        $p = $this->phrases[$phraseStr];
                        $mult = $getNegationMultiplier($i); // use first word's index for distance
                        
                        $totalRawScore += ($p['weight'] * $p['polarity'] * $mult);
                        $matchedTokenCount += $len;
                        
                        // Mark tokens as consumed
                        for ($k = 0; $k < $len; $k++) {
                            $consumed[$i + $k] = true;
                        }
                    }
                }
            }
        }

        // Step 2: Unigram Pass
        for ($i = 0; $i < $numTokens; $i++) {
            if (!$consumed[$i]) {
                $token = $tokens[$i];
                if (in_array($token, $this->negations)) {
                    // Standalone negation trigger: takes priority, marked consumed, never scored as sentiment or counted towards matched tokens
                    $consumed[$i] = true;
                } elseif (isset($this->lexicon[$token])) {
                    $p = $this->lexicon[$token];
                    $mult = $getNegationMultiplier($i);
                    
                    $totalRawScore += ($p['weight'] * $p['polarity'] * $mult);
                    $matchedTokenCount += 1;
                    $consumed[$i] = true;
                }
            }
        }

        // Step 3.5: Entity Multiplier
        $entityMultiplier = 1.0;
        for ($i = 0; $i < $numTokens; $i++) {
            $token = $tokens[$i];
            if (isset($this->entities[$token])) {
                $matchedEntityCount++;
                $entityMultiplier = max($entityMultiplier, $this->entities[$token]);
                if (!$consumed[$i]) {
                    $matchedTokenCount += 1; // Count entity towards length penalty
                    $consumed[$i] = true;
                }
            }
        }
        $entityMultiplier = min($entityMultiplier, 1.8);

        // Step 4: Continuous Score Normalization with Length Penalty
        $normalizedScore = 0.0;
        if ($matchedTokenCount > 0) {
            $totalRawScore *= $entityMultiplier;
            $normalizedScore = $totalRawScore / sqrt(1 + $matchedTokenCount);
        }

        // Dead-zone thresholding
        $absScore = abs($normalizedScore);
        $tag = 'Neutral';
        $confidence = 'Low';

        if ($absScore >= 0.60) {
            $tag = $normalizedScore > 0 ? 'Bullish' : 'Bearish';
            $confidence = 'High';
        } elseif ($absScore >= 0.20) {
            $tag = $normalizedScore > 0 ? 'Bullish' : 'Bearish';
            $confidence = 'Medium';
        }

        return [
            'score' => round($normalizedScore, 4),
            'tag' => $tag,
            'confidence' => $confidence,
            'matched_tokens' => $matchedTokenCount,
            'is_relevant' => ($matchedEntityCount > 0) // Strict entity filter
        ];
    }
}
?>
