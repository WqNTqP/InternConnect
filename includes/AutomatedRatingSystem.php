<?php
/**
 * Automated Rating System with Anti-Cheating Features
 * Combines academic grades, content analysis, and AI detection
 */

class AutomatedRatingSystem {
    
    /**
     * Main function to rate a response with comprehensive analysis
     */
    public static function autoRateResponseWithAntiCheating($questionId, $answer, $category, $studentGrades) {
        $answer = trim($answer);
        $baseScore = 1;
        
        // 1. GET ACADEMIC PERFORMANCE FOR CATEGORY
        $categoryGrades = self::getCategoryGrades($category, $studentGrades);
        $avgGrade = array_sum($categoryGrades) / count($categoryGrades);
        
        // 2. DETECT POTENTIAL AI-GENERATED CONTENT
        $aiSuspicionScore = self::detectAIContent($answer);
        
        // 3. BASIC RESPONSE QUALITY
        $wordCount = str_word_count($answer);
        $sentenceCount = preg_match_all('/[.!?]+/', $answer);
        $avgWordsPerSentence = $sentenceCount > 0 ? $wordCount / $sentenceCount : 0;
        
        if ($wordCount < 15 || $sentenceCount < 2) {
            return max(1, min(2, ($wordCount * 0.1) + (self::getGradeBonus($avgGrade) * 0.3)));
        }
        
        $baseScore = 2;
        
        // 4. CONTENT QUALITY ANALYSIS
        $contentScore = self::evaluateContentQuality($answer, $category);
        
        // 5. GRADE-BASED ADJUSTMENT
        $gradeBonus = self::getGradeBonus($avgGrade);
        
        // 6. COMPLEXITY vs GRADE ALIGNMENT CHECK
        $complexityScore = self::assessComplexity($answer);
        $complexityAlignment = self::checkComplexityGradeAlignment($complexityScore, $avgGrade);
        
        // 7. APPLY AI SUSPICION PENALTIES
        $aiPenalty = self::calculateAIPenalty($aiSuspicionScore, $complexityScore, $avgGrade);
        
        // 8. AUTHENTICITY BONUS/PENALTY
        $authenticityScore = self::assessAuthenticity($answer, $category);
        
        $finalScore = $baseScore + $contentScore + $gradeBonus + $complexityAlignment + $authenticityScore - $aiPenalty;
        
        return min(5, max(1, round($finalScore, 1)));
    }
    
    /**
     * Detect AI-generated content patterns
     */
    public static function detectAIContent($response) {
        $suspicionScore = 0;
        
        // Common ChatGPT patterns and phrases
        $aiIndicators = [
            // Formal/academic phrases
            '/\b(furthermore|moreover|additionally|consequently|thus|therefore|hence)\b/i' => 0.3,
            '/\b(it is important to note|it should be noted|it is worth mentioning)\b/i' => 0.5,
            '/\b(in conclusion|to summarize|in summary|overall)\b/i' => 0.4,
            
            // Structured responses
            '/\b(first and foremost|first of all|to begin with)\b/i' => 0.4,
            '/\b(on the other hand|conversely|alternatively)\b/i' => 0.3,
            '/\b(last but not least|finally and most importantly)\b/i' => 0.5,
            
            // AI-like explanatory patterns
            '/\b(this approach|this strategy|this method|this technique)\b/i' => 0.2,
            '/\b(key aspects|crucial elements|essential components)\b/i' => 0.4,
            '/\b(comprehensive understanding|holistic approach)\b/i' => 0.5,
            
            // Overly professional language for students
            '/\b(leverage|utilize|facilitate|optimize|enhance|implement)\b/i' => 0.3,
            '/\b(stakeholders|deliverables|best practices|synergy)\b/i' => 0.4,
            
            // Perfect structure indicators
            '/^\d+\.\s|\n\d+\.\s/' => 0.4, // Numbered lists
            '/^[A-Z][^.!?]*:[^.!?]*[.!?]/' => 0.3, // Colon-based structure
        ];
        
        foreach ($aiIndicators as $pattern => $weight) {
            if (preg_match($pattern, $response)) {
                $suspicionScore += $weight;
            }
        }
        
        // Check for unnatural perfection
        $sentences = preg_split('/[.!?]+/', $response);
        $sentences = array_filter($sentences, 'trim');
        
        if (count($sentences) >= 3) {
            $avgSentenceLength = array_sum(array_map('str_word_count', $sentences)) / count($sentences);
            
            // AI tends to write very consistent sentence lengths
            $lengthVariation = 0;
            foreach ($sentences as $sentence) {
                $lengthVariation += abs(str_word_count($sentence) - $avgSentenceLength);
            }
            $lengthVariation = $lengthVariation / count($sentences);
            
            if ($lengthVariation < 3 && $avgSentenceLength > 15) {
                $suspicionScore += 0.4; // Too consistent = suspicious
            }
        }
        
        // Grammar perfection check (unusual for students)
        $grammarErrors = 0;
        if (!preg_match('/[A-Z]/', $response)) $grammarErrors++;
        if (preg_match('/\s{2,}/', $response)) $grammarErrors++;
        if (preg_match('/[a-z][.!?][a-z]/', $response)) $grammarErrors++;
        
        if ($grammarErrors == 0 && str_word_count($response) > 50) {
            $suspicionScore += 0.3; // Perfect grammar suspicious for students
        }
        
        return min(2, $suspicionScore); // Cap at 2.0
    }
    
    /**
     * Assess response complexity
     */
    private static function assessComplexity($answer) {
        $complexityScore = 0;
        
        // Vocabulary sophistication
        $sophisticatedWords = [
            'articulate', 'comprehensive', 'facilitate', 'implement', 'optimize', 'leverage',
            'paradigm', 'methodology', 'systematic', 'analytical', 'strategic', 'innovative',
            'collaborative', 'synergistic', 'multifaceted', 'holistic', 'nuanced', 'intricate'
        ];
        
        foreach ($sophisticatedWords as $word) {
            if (stripos($answer, $word) !== false) {
                $complexityScore += 0.2;
            }
        }
        
        // Sentence structure complexity
        $complexStructures = [
            '/\b(although|despite|whereas|nevertheless|however)\b/i' => 0.3,
            '/\b(not only.*but also|both.*and)\b/i' => 0.4,
            '/\b(which|that|who).*,/i' => 0.2, // Relative clauses
        ];
        
        foreach ($complexStructures as $pattern => $weight) {
            if (preg_match($pattern, $answer)) {
                $complexityScore += $weight;
            }
        }
        
        return min(3, $complexityScore);
    }
    
    /**
     * Check if complexity aligns with grade level
     */
    private static function checkComplexityGradeAlignment($complexityScore, $avgGrade) {
        // Students with lower grades shouldn't write overly complex responses
        $expectedComplexity = 0;
        if ($avgGrade >= 90) $expectedComplexity = 2.5;
        elseif ($avgGrade >= 85) $expectedComplexity = 2.0;
        elseif ($avgGrade >= 80) $expectedComplexity = 1.5;
        elseif ($avgGrade >= 75) $expectedComplexity = 1.0;
        else $expectedComplexity = 0.5;
        
        $complexityDiff = $complexityScore - $expectedComplexity;
        
        if ($complexityDiff > 1.5) {
            return -1.0; // Way too complex for grade level - suspicious
        } elseif ($complexityDiff > 1.0) {
            return -0.5; // Somewhat suspicious
        } elseif ($complexityDiff < -0.5) {
            return -0.3; // Below expected level
        }
        
        return 0.2; // Good alignment
    }
    
    /**
     * Calculate AI penalty based on suspicion and complexity
     */
    private static function calculateAIPenalty($aiSuspicionScore, $complexityScore, $avgGrade) {
        $penalty = 0;
        
        // High AI suspicion
        if ($aiSuspicionScore > 1.5) {
            $penalty += 2.0; // Major penalty
        } elseif ($aiSuspicionScore > 1.0) {
            $penalty += 1.0; // Moderate penalty
        } elseif ($aiSuspicionScore > 0.7) {
            $penalty += 0.5; // Minor penalty
        }
        
        // Complexity mismatch penalty
        if ($complexityScore > 2.0 && $avgGrade < 80) {
            $penalty += 1.5; // Low grades + high complexity = very suspicious
        } elseif ($complexityScore > 1.5 && $avgGrade < 75) {
            $penalty += 1.0;
        }
        
        return $penalty;
    }
    
    /**
     * Assess response authenticity
     */
    private static function assessAuthenticity($answer, $category) {
        $authenticityScore = 0;
        
        // Look for personal, authentic markers
        $personalMarkers = [
            '/\b(I remember|my experience|when I|I felt|I realized)\b/i' => 0.3,
            '/\b(my team|our group|my classmates|we decided)\b/i' => 0.2,
            '/\b(it was difficult|I struggled|challenging|hard time)\b/i' => 0.3,
            '/\b(I learned|taught me|helped me understand)\b/i' => 0.2,
        ];
        
        foreach ($personalMarkers as $pattern => $weight) {
            if (preg_match($pattern, $answer)) {
                $authenticityScore += $weight;
            }
        }
        
        // Simple, natural language bonus
        $simpleWords = str_word_count($answer);
        $complexWords = 0;
        $words = str_word_count($answer, 1);
        
        foreach ($words as $word) {
            if (strlen($word) > 8) {
                $complexWords++;
            }
        }
        
        $complexityRatio = $simpleWords > 0 ? $complexWords / $simpleWords : 0;
        if ($complexityRatio < 0.2) { // Less than 20% complex words
            $authenticityScore += 0.3;
        }
        
        return min(1, $authenticityScore);
    }
    
    /**
     * Evaluate content quality
     */
    private static function evaluateContentQuality($answer, $category) {
        $contentScore = 0;
        $wordCount = str_word_count($answer);
        
        // Length-based scoring
        if ($wordCount >= 80) $contentScore += 1.0;
        elseif ($wordCount >= 50) $contentScore += 0.7;
        elseif ($wordCount >= 30) $contentScore += 0.4;
        
        // Specific examples
        if (preg_match('/\b(example|instance|time when|situation where)\b/i', $answer)) {
            $contentScore += 0.5;
        }
        
        // Category-relevant content (more specific scenarios)
        $categoryScenarios = [
            'Soft Skills' => [
                '/\b(team project|group work|deadline pressure|conflict resolution)\b/i',
                '/\b(time management|prioritizing|organizing|planning)\b/i',
                '/\b(feedback|criticism|improvement|learning from mistakes)\b/i'
            ],
            'Communication Skills' => [
                '/\b(presentation|explaining|teaching|clarifying)\b/i',
                '/\b(misunderstanding|confusion|making sure they understand)\b/i',
                '/\b(listening|asking questions|body language|tone)\b/i'
            ],
            'Technical Skills' => [
                '/\b(debugging|testing|code review|version control)\b/i',
                '/\b(documentation|commenting|readable code|best practices)\b/i',
                '/\b(performance|security|scalability|optimization)\b/i'
            ]
        ];
        
        $scenarios = $categoryScenarios[$category] ?? [];
        foreach ($scenarios as $pattern) {
            if (preg_match($pattern, $answer)) {
                $contentScore += 0.2;
            }
        }
        
        return min(2, $contentScore);
    }
    
    /**
     * Get grade bonus based on average
     */
    private static function getGradeBonus($avgGrade) {
        if ($avgGrade >= 95) return 1.5;
        elseif ($avgGrade >= 90) return 1.2;
        elseif ($avgGrade >= 85) return 0.8;
        elseif ($avgGrade >= 80) return 0.4;
        elseif ($avgGrade >= 75) return 0.1;
        else return -0.2;
    }
    
    /**
     * Get category-specific grades
     */
    private static function getCategoryGrades($category, $grades) {
        $gradeMapping = [
            'Soft Skills' => ['CC 102', 'CC 103', 'PF 101', 'SP 101', 'CAP 101', 'CAP 102'],
            'Communication Skills' => ['SP 101', 'CAP 101', 'CAP 102', 'HCI 101', 'HCI 102'],
            'Technical Skills' => ['CC 104', 'CC 106', 'CC 105', 'IPT 101', 'IPT 102', 'WS 101', 'NET 101', 'NET 102', 'IAS 101', 'IAS 102']
        ];
        
        $categorySubjects = $gradeMapping[$category] ?? [];
        $categoryGrades = [];
        
        foreach ($categorySubjects as $subject) {
            if (isset($grades[$subject]) && $grades[$subject] > 0) {
                $categoryGrades[] = $grades[$subject];
            }
        }
        
        if (empty($categoryGrades)) {
            $allGrades = array_filter($grades, function($grade) { return $grade > 0; });
            return [$allGrades ? array_sum($allGrades) / count($allGrades) : 75];
        }
        
        return $categoryGrades;
    }
    
    /**
     * Generate detailed analysis report for debugging
     */
    public static function generateAnalysisReport($questionId, $answer, $category, $studentGrades) {
        $report = [
            'basic_stats' => [
                'word_count' => str_word_count($answer),
                'sentence_count' => preg_match_all('/[.!?]+/', $answer),
                'character_count' => strlen($answer)
            ],
            'grade_analysis' => [
                'category_grades' => self::getCategoryGrades($category, $studentGrades),
                'average_grade' => 0,
                'grade_bonus' => 0
            ],
            'ai_detection' => [
                'suspicion_score' => self::detectAIContent($answer),
                'complexity_score' => self::assessComplexity($answer),
                'authenticity_score' => self::assessAuthenticity($answer, $category)
            ],
            'content_quality' => [
                'quality_score' => self::evaluateContentQuality($answer, $category)
            ],
            'final_rating' => self::autoRateResponseWithAntiCheating($questionId, $answer, $category, $studentGrades)
        ];
        
        // Calculate grade analysis
        $categoryGrades = self::getCategoryGrades($category, $studentGrades);
        $report['grade_analysis']['average_grade'] = array_sum($categoryGrades) / count($categoryGrades);
        $report['grade_analysis']['grade_bonus'] = self::getGradeBonus($report['grade_analysis']['average_grade']);
        
        return $report;
    }
}