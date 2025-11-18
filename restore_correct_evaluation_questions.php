<?php
/**
 * Restore CORRECT Evaluation Questions from Backup
 * This restores the actual questions that were in your database
 */

require_once __DIR__ . '/config/path_config.php';
require_once PathConfig::getDatabasePath();

try {
    $db = new Database();
    $conn = $db->conn;
    
    if ($conn === null) {
        die("❌ Database connection failed.\n");
    }
    
    echo "🔄 RESTORING CORRECT EVALUATION QUESTIONS\n";
    echo "=========================================\n\n";
    
    // First, clear the incorrect questions
    echo "🗑️  Clearing incorrect questions...\n";
    $conn->exec("DELETE FROM evaluation_questions");
    $conn->exec("ALTER TABLE evaluation_questions AUTO_INCREMENT = 1");
    
    // Define the CORRECT evaluation questions from your backup
    $correctQuestions = [
        // Soft Skills (10 questions)
        ['id' => 1, 'category' => 'Soft Skills', 'question_text' => 'Describe a time you worked effectively in a team.'],
        ['id' => 2, 'category' => 'Soft Skills', 'question_text' => 'How do you handle stressful situations at work or school?'],
        ['id' => 3, 'category' => 'Soft Skills', 'question_text' => 'Give an example of how you adapted to a major change.'],
        ['id' => 4, 'category' => 'Soft Skills', 'question_text' => 'How do you approach solving a difficult problem?'],
        ['id' => 5, 'category' => 'Soft Skills', 'question_text' => 'What motivates you to achieve your goals?'],
        ['id' => 6, 'category' => 'Soft Skills', 'question_text' => 'How do you manage your time when working on multiple tasks?'],
        ['id' => 7, 'category' => 'Soft Skills', 'question_text' => 'Describe a situation where you showed leadership.'],
        ['id' => 8, 'category' => 'Soft Skills', 'question_text' => 'How do you respond to constructive criticism?'],
        ['id' => 9, 'category' => 'Soft Skills', 'question_text' => 'What steps do you take to stay organized?'],
        ['id' => 10, 'category' => 'Soft Skills', 'question_text' => 'How do you maintain a positive attitude during setbacks?'],
        
        // Communication Skills (10 questions)
        ['id' => 11, 'category' => 'Communication Skills', 'question_text' => 'Explain a technical concept to someone with no background in the subject.'],
        ['id' => 12, 'category' => 'Communication Skills', 'question_text' => 'How do you ensure your message is clearly understood?'],
        ['id' => 13, 'category' => 'Communication Skills', 'question_text' => 'Describe a time you resolved a misunderstanding.'],
        ['id' => 14, 'category' => 'Communication Skills', 'question_text' => 'How do you actively listen during conversations?'],
        ['id' => 15, 'category' => 'Communication Skills', 'question_text' => 'What strategies do you use to communicate in a group setting?'],
        ['id' => 16, 'category' => 'Communication Skills', 'question_text' => 'How do you handle feedback from others?'],
        ['id' => 17, 'category' => 'Communication Skills', 'question_text' => 'Describe a situation where you had to persuade someone.'],
        ['id' => 18, 'category' => 'Communication Skills', 'question_text' => 'How do you adjust your communication style for different audiences?'],
        ['id' => 19, 'category' => 'Communication Skills', 'question_text' => 'What role does non-verbal communication play in your interactions?'],
        ['id' => 20, 'category' => 'Communication Skills', 'question_text' => 'How do you clarify instructions or expectations when they are unclear?'],
        
        // Personal and Interpersonal Skills (7 questions)
        ['id' => 21, 'category' => 'Personal and Interpersonal Skills', 'question_text' => 'Demonstrated the ability to integrate theories learned in school and the practical work in your company.'],
        ['id' => 22, 'category' => 'Personal and Interpersonal Skills', 'question_text' => 'Demonstrated evidence of growth as a result of his apprenticeship.'],
        ['id' => 23, 'category' => 'Personal and Interpersonal Skills', 'question_text' => 'Demonstrated assertiveness and cleverness to new endeavors in the course of his/her training.'],
        ['id' => 24, 'category' => 'Personal and Interpersonal Skills', 'question_text' => 'Demonstrated adequate knowledge of work done.'],
        ['id' => 25, 'category' => 'Personal and Interpersonal Skills', 'question_text' => 'Demonstrated promptness and active attendance.'],
        ['id' => 26, 'category' => 'Personal and Interpersonal Skills', 'question_text' => 'Demonstrated skills in inter-personal relations.'],
        ['id' => 27, 'category' => 'Personal and Interpersonal Skills', 'question_text' => 'Demonstrated overall performance proficiency.'],
        
        // Technical Skills (10 questions)
        ['id' => 28, 'category' => 'Technical Skills', 'question_text' => 'Walk me through your systematic approach to debugging a program that produces unexpected results or crashes intermittently.'],
        ['id' => 29, 'category' => 'Technical Skills', 'question_text' => 'How do you ensure your code is readable and maintainable? What specific practices do you follow?'],
        ['id' => 30, 'category' => 'Technical Skills', 'question_text' => 'Describe your workflow when collaborating on code with other developers using version control systems like Git.'],
        ['id' => 31, 'category' => 'Technical Skills', 'question_text' => 'What types of testing do you consider essential for ensuring software quality? How do you approach writing test cases?'],
        ['id' => 32, 'category' => 'Technical Skills', 'question_text' => 'How would you approach optimizing a slow-performing database query or application feature?'],
        ['id' => 33, 'category' => 'Technical Skills', 'question_text' => 'Explain how you would break down a complex programming problem into smaller, manageable components.'],
        ['id' => 34, 'category' => 'Technical Skills', 'question_text' => 'What security considerations do you keep in mind when developing web applications?'],
        ['id' => 35, 'category' => 'Technical Skills', 'question_text' => 'How do you stay updated with new technologies and programming best practices in your field?'],
        ['id' => 36, 'category' => 'Technical Skills', 'question_text' => 'Describe your approach to code review. What do you look for when reviewing others code?'],
        ['id' => 37, 'category' => 'Technical Skills', 'question_text' => 'How would you design a system to handle increasing user load and ensure scalability?']
    ];
    
    // Start transaction
    $conn->beginTransaction();
    
    // Prepare insert statement with explicit IDs
    $insertStmt = $conn->prepare("
        INSERT INTO evaluation_questions (question_id, category, question_text, status) 
        VALUES (?, ?, ?, 'active')
    ");
    
    $insertedCount = 0;
    $categoryCounts = [];
    
    foreach ($correctQuestions as $question) {
        try {
            $insertStmt->execute([
                $question['id'],
                $question['category'],
                $question['question_text']
            ]);
            
            $insertedCount++;
            
            // Count per category
            if (!isset($categoryCounts[$question['category']])) {
                $categoryCounts[$question['category']] = 0;
            }
            $categoryCounts[$question['category']]++;
            
            echo "✅ Added ID {$question['id']}: " . $question['category'] . " - " . 
                 substr($question['question_text'], 0, 50) . "...\n";
            
        } catch (Exception $e) {
            echo "❌ Error adding question ID {$question['id']}: " . $e->getMessage() . "\n";
        }
    }
    
    // Set the auto-increment to continue from 38
    $conn->exec("ALTER TABLE evaluation_questions AUTO_INCREMENT = 38");
    
    // Commit transaction
    $conn->commit();
    
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "📊 RESTORATION SUMMARY (CORRECT DATA)\n";
    echo str_repeat("=", 70) . "\n";
    echo "✅ Total questions restored: $insertedCount\n\n";
    
    echo "📋 Questions per category (as per your original data):\n";
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
    echo "\n🎉 CORRECT evaluation questions successfully restored from backup!\n";
    echo "💡 These are the exact questions that were in your original database.\n";
    
} catch (Exception $e) {
    // Rollback on error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo "❌ Error restoring questions: " . $e->getMessage() . "\n";
    exit(1);
}
?>