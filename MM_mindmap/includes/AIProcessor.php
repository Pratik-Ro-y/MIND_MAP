
<?php
// includes/AIProcessor.php
class AIProcessor {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function analyzeContent($content) {
        // Basic content analysis for mind map generation
        $analysis = [
            'main_topics' => $this->extractMainTopics($content),
            'key_concepts' => $this->extractKeyConcepts($content),
            'sentiment' => $this->analyzeSentiment($content),
            'summary' => $this->generateSummary($content),
            'suggested_structure' => $this->suggestMindMapStructure($content)
        ];
        
        return $analysis;
    }
    
    private function extractMainTopics($content) {
        // Simple keyword extraction based on frequency and position
        $words = str_word_count(strtolower($content), 1);
        $stopWords = ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those'];
        
        $filteredWords = array_filter($words, function($word) use ($stopWords) {
            return strlen($word) > 3 && !in_array($word, $stopWords);
        });
        
        $wordFreq = array_count_values($filteredWords);
        arsort($wordFreq);
        
        return array_slice(array_keys($wordFreq), 0, 10);
    }
    
    private function extractKeyConcepts($content) {
        // Extract sentences that might contain key concepts
        $sentences = preg_split('/[.!?]+/', $content);
        $concepts = [];
        
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) > 20 && strlen($sentence) < 100) {
                // Look for sentences with important indicators
                if (preg_match('/\b(important|key|main|primary|essential|critical|significant)\b/i', $sentence)) {
                    $concepts[] = $sentence;
                }
            }
        }
        
        return array_slice($concepts, 0, 5);
    }
    
    private function analyzeSentiment($content) {
        // Basic sentiment analysis
        $positiveWords = ['good', 'great', 'excellent', 'amazing', 'wonderful', 'fantastic', 'positive', 'success', 'achievement', 'opportunity'];
        $negativeWords = ['bad', 'terrible', 'awful', 'horrible', 'negative', 'problem', 'issue', 'failure', 'risk', 'challenge'];
        
        $words = str_word_count(strtolower($content), 1);
        $positiveCount = count(array_intersect($words, $positiveWords));
        $negativeCount = count(array_intersect($words, $negativeWords));
        
        if ($positiveCount > $negativeCount) {
            return 'positive';
        } elseif ($negativeCount > $positiveCount) {
            return 'negative';
        }
        
        return 'neutral';
    }
    
    private function generateSummary($content) {
        // Generate a basic summary by extracting first and key sentences
        $sentences = preg_split('/[.!?]+/', $content);
        $summary = [];
        
        // Always include first sentence if meaningful
        if (!empty($sentences[0]) && strlen(trim($sentences[0])) > 10) {
            $summary[] = trim($sentences[0]);
        }
        
        // Add sentences with key indicators
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) > 20) {
                if (preg_match('/\b(conclusion|summary|result|therefore|finally|overall)\b/i', $sentence)) {
                    $summary[] = $sentence;
                }
            }
            
            if (count($summary) >= 3) break;
        }
        
        return implode('. ', array_unique($summary));
    }
    
    private function suggestMindMapStructure($content) {
        $mainTopics = $this->extractMainTopics($content);
        $structure = [
            'central_topic' => !empty($mainTopics) ? ucfirst($mainTopics[0]) : 'Main Topic',
            'branches' => []
        ];
        
        // Create branches for top topics
        for ($i = 1; $i < min(6, count($mainTopics)); $i++) {
            $structure['branches'][] = [
                'text' => ucfirst($mainTopics[$i]),
                'color' => $this->generateColor($i),
                'position' => $this->calculatePosition($i, count($mainTopics))
            ];
        }
        
        return $structure;
    }
    
    private function generateColor($index) {
        $colors = ['#667eea', '#4facfe', '#43e97b', '#fa709a', '#f093fb', '#feca57', '#ff6b6b'];
        return $colors[$index % count($colors)];
    }
    
    private function calculatePosition($index, $total) {
        $angle = ($index * 360 / $total) * (M_PI / 180);
        $radius = 200;
        
        return [
            'x' => 600 + ($radius * cos($angle)),
            'y' => 400 + ($radius * sin($angle))
        ];
    }
    
    public function generateSuggestions($mindMapId, $nodeText) {
        // Generate AI suggestions for a specific node
        $suggestions = [];
        
        // Get existing nodes to avoid duplicates
        $existingNodes = $this->db->fetchAll(
            "SELECT node_text FROM nodes WHERE map_id = (SELECT map_id FROM nodes WHERE node_id = ?)",
            [$mindMapId]
        );
        
        $existingTexts = array_column($existingNodes, 'node_text');
        
        // Generate contextual suggestions based on node text
        $contextSuggestions = $this->getContextualSuggestions($nodeText);
        
        foreach ($contextSuggestions as $suggestion) {
            if (!in_array($suggestion, $existingTexts)) {
                $suggestions[] = $suggestion;
            }
        }
        
        return array_slice($suggestions, 0, 5);
    }
    
    private function getContextualSuggestions($nodeText) {
        $text = strtolower($nodeText);
        $suggestions = [];
        
        // Business context
        if (preg_match('/\b(business|company|market|strategy)\b/', $text)) {
            $suggestions = array_merge($suggestions, [
                'Market Analysis',
                'Competitive Landscape',
                'Revenue Streams',
                'Customer Segments',
                'Value Proposition'
            ]);
        }
        
        // Project context
        if (preg_match('/\b(project|plan|task|goal)\b/', $text)) {
            $suggestions = array_merge($suggestions, [
                'Timeline',
                'Resources',
                'Milestones',
                'Risk Assessment',
                'Stakeholders'
            ]);
        }
        
        // Learning context
        if (preg_match('/\b(learn|study|education|course)\b/', $text)) {
            $suggestions = array_merge($suggestions, [
                'Key Concepts',
                'Practice Exercises',
                'Resources',
                'Prerequisites',
                'Assessment'
            ]);
        }
        
        // Default suggestions
        if (empty($suggestions)) {
            $suggestions = [
                'Details',
                'Examples',
                'Benefits',
                'Challenges',
                'Next Steps'
            ];
        }
        
        return $suggestions;
    }
}
?>
