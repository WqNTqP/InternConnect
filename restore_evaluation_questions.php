<?php
/**
 * Restore Evaluation Questions
 * Adds back the 5 category evaluation questions that were cleaned
 */

require_once __DIR__ . '/config/path_config.php';
require_once PathConfig::getDatabasePath();

try {
    $db = new Database();
    $conn = $db->conn;
    
    if ($conn === null) {
        die("❌ Database connection failed.\n");
    }
    
    echo "📝 RESTORING EVALUATION QUESTIONS\n";
    echo "=================================\n\n";
    
    // Define the 5 categories of evaluation questions
    $evaluationQuestions = [
        // System Development Category
        ['category' => 'System Development', 'question_text' => 'Demonstrated knowledge in programming languages and frameworks.'],
        ['category' => 'System Development', 'question_text' => 'Applied software development methodologies effectively.'],
        ['category' => 'System Development', 'question_text' => 'Showed proficiency in database design and management.'],
        ['category' => 'System Development', 'question_text' => 'Implemented proper version control and documentation practices.'],
        ['category' => 'System Development', 'question_text' => 'Demonstrated problem-solving skills in system architecture.'],
        ['category' => 'System Development', 'question_text' => 'Applied security principles in system development.'],
        ['category' => 'System Development', 'question_text' => 'Demonstrated overall system development proficiency.'],
        
        // Research Category
        ['category' => 'Research', 'question_text' => 'Conducted thorough literature review and analysis.'],
        ['category' => 'Research', 'question_text' => 'Applied appropriate research methodologies.'],
        ['category' => 'Research', 'question_text' => 'Demonstrated critical thinking and analytical skills.'],
        ['category' => 'Research', 'question_text' => 'Presented findings clearly and professionally.'],
        ['category' => 'Research', 'question_text' => 'Showed innovation and creativity in research approach.'],
        ['category' => 'Research', 'question_text' => 'Applied ethical research practices.'],
        ['category' => 'Research', 'question_text' => 'Demonstrated overall research proficiency.'],
        
        // Technical Support Category
        ['category' => 'Technical Support', 'question_text' => 'Demonstrated technical troubleshooting abilities.'],
        ['category' => 'Technical Support', 'question_text' => 'Provided effective customer service and support.'],
        ['category' => 'Technical Support', 'question_text' => 'Applied systematic problem-solving approaches.'],
        ['category' => 'Technical Support', 'question_text' => 'Showed proficiency in hardware and software diagnostics.'],
        ['category' => 'Technical Support', 'question_text' => 'Demonstrated knowledge of network troubleshooting.'],
        ['category' => 'Technical Support', 'question_text' => 'Applied proper documentation and ticketing practices.'],
        ['category' => 'Technical Support', 'question_text' => 'Demonstrated overall technical support proficiency.'],
        
        // Business Operations Category
        ['category' => 'Business Operations', 'question_text' => 'Understood business processes and workflows.'],
        ['category' => 'Business Operations', 'question_text' => 'Applied project management principles effectively.'],
        ['category' => 'Business Operations', 'question_text' => 'Demonstrated knowledge of business analysis techniques.'],
        ['category' => 'Business Operations', 'question_text' => 'Showed understanding of organizational structures.'],
        ['category' => 'Business Operations', 'question_text' => 'Applied effective resource management practices.'],
        ['category' => 'Business Operations', 'question_text' => 'Demonstrated knowledge of quality assurance processes.'],
        ['category' => 'Business Operations', 'question_text' => 'Demonstrated overall business operations proficiency.'],
        
        // Personal and Interpersonal Skills Category
        ['category' => 'Personal and Interpersonal Skills', 'question_text' => 'Demonstrated effective communication skills.'],
        ['category' => 'Personal and Interpersonal Skills', 'question_text' => 'Showed teamwork and collaboration abilities.'],
        ['category' => 'Personal and Interpersonal Skills', 'question_text' => 'Applied leadership skills when appropriate.'],
        ['category' => 'Personal and Interpersonal Skills', 'question_text' => 'Demonstrated adaptability and flexibility.'],
        ['category' => 'Personal and Interpersonal Skills', 'question_text' => 'Showed initiative and self-motivation.'],
        ['category' => 'Personal and Interpersonal Skills', 'question_text' => 'Demonstrated punctuality and attendance.'],
        ['category' => 'Personal and Interpersonal Skills', 'question_text' => 'Demonstrated overall interpersonal proficiency.']
    ];
    
    // Start transaction
    $conn->beginTransaction();
    
    // Prepare insert statement
    $insertStmt = $conn->prepare("
        INSERT INTO evaluation_questions (category, question_text) 
        VALUES (?, ?)
    ");
    
    $insertedCount = 0;
    $categoryCounts = [];
    
    foreach ($evaluationQuestions as $question) {
        try {
            $insertStmt->execute([
                $question['category'],
                $question['question_text']
            ]);
            
            $insertedCount++;
            
            // Count per category
            if (!isset($categoryCounts[$question['category']])) {
                $categoryCounts[$question['category']] = 0;
            }
            $categoryCounts[$question['category']]++;
            
            echo "✅ Added: " . $question['category'] . " - " . 
                 substr($question['question_text'], 0, 50) . "...\n";
            
        } catch (Exception $e) {
            echo "❌ Error adding question: " . $e->getMessage() . "\n";
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "📊 RESTORATION SUMMARY\n";
    echo str_repeat("=", 60) . "\n";
    echo "✅ Total questions added: $insertedCount\n\n";
    
    echo "📋 Questions per category:\n";
    foreach ($categoryCounts as $category => $count) {
        echo "   • $category: $count questions\n";
    }
    
    // Verify the restoration
    echo "\n🔍 VERIFICATION:\n";
    $verifyStmt = $conn->query("
        SELECT category, COUNT(*) as count 
        FROM evaluation_questions 
        GROUP BY category 
        ORDER BY category
    ");
    
    $totalVerified = 0;
    while ($row = $verifyStmt->fetch(PDO::FETCH_ASSOC)) {
        echo "   ✓ {$row['category']}: {$row['count']} questions\n";
        $totalVerified += $row['count'];
    }
    
    echo "\n📈 Total verified in database: $totalVerified questions\n";
    echo "\n🎉 Evaluation questions successfully restored!\n";
    echo "💡 The system is now ready for student evaluations.\n";
    
} catch (Exception $e) {
    // Rollback on error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo "❌ Error restoring questions: " . $e->getMessage() . "\n";
    exit(1);
}
?>