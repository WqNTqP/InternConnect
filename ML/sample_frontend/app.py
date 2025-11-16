from flask import Flask, request, jsonify
from flask_cors import CORS
import joblib
import pandas as pd
import numpy as np
import random
from sklearn.preprocessing import LabelEncoder
import os
from dotenv import load_dotenv

# Try to import requests for HTTP communication, install if not available
try:
    import requests
    print("✅ requests library is available")
except ImportError:
    print("⚠️ requests not found, installing...")
    import subprocess
    import sys
    subprocess.check_call([sys.executable, "-m", "pip", "install", "requests"])
    import requests
    print("✅ requests installed successfully")

# Load environment variables from .env file
env_path = os.path.join(os.path.dirname(__file__), '..', '..', '.env')
load_dotenv(env_path)

app = Flask(__name__)
CORS(app, resources={r"/*": {"origins": "*"}})

# Load model and label encoder with error handling
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
model_path = os.path.join(BASE_DIR, '..', 'model', 'pre-assessment.joblib')

clf = None
model_loaded = False

try:
    clf = joblib.load(model_path)
    model_loaded = True
    print(f"✅ Model loaded successfully from: {model_path}")
except Exception as e:
    print(f"❌ Failed to load model: {str(e)}")
    print(f"Model path attempted: {model_path}")
    print("⚠️ Flask API will start but predictions will be unavailable")
    model_loaded = False

# Use HTTP requests to get data from live database through PHP bridge
import requests

print("Attempting to connect to LIVE database through PHP bridge...")

# Try to get data from live database via HTTP
df_ref = None
le = None
database_connected = False
feature_cols = []

try:
    # Environment-aware base URL configuration
    is_render = 'RENDER' in os.environ or 'RENDER_SERVICE_ID' in os.environ
    print(f"🔍 Environment detection - Is Render: {is_render}")
    
    if is_render:
        # Production environment (Render)
        base_url = 'https://internconnect-kjzb.onrender.com/api/database_bridge.php'
        print(f"🚀 Using production URL: {base_url}")
    else:
        # Local development environment - try multiple common ports
        possible_bases = [
            'http://localhost:80/InternConnect/api/database_bridge.php',
            'http://localhost/InternConnect/api/database_bridge.php',
            'http://127.0.0.1:80/InternConnect/api/database_bridge.php',
            'http://127.0.0.1/InternConnect/api/database_bridge.php'
        ]
        
        base_url = None
        for test_url in possible_bases:
            try:
                test_response = requests.get(test_url + '?action=test', timeout=3)
                if test_response.status_code == 200:
                    base_url = test_url
                    print(f"✅ Found working local server at: {base_url}")
                    break
            except:
                continue
        
        if not base_url:
            base_url = 'http://localhost/InternConnect/api/database_bridge.php'  # fallback
            print(f"⚠️ Using fallback URL: {base_url}")
    
    print(f"🔄 Fetching feature columns from live database using: {base_url}")
    response = requests.get(f"{base_url}?action=get_feature_columns", timeout=10)
    
    if response.status_code == 200:
        feature_data = response.json()
        if feature_data['success']:
            feature_cols = feature_data['feature_columns']
            print(f"✅ Got {len(feature_cols)} feature columns from live database")
            print(f"📋 Feature columns: {feature_cols}")
        else:
            raise Exception(f"PHP bridge error: {feature_data.get('message', 'Unknown error')}")
    else:
        raise Exception(f"HTTP error: {response.status_code}")
    
    # Get placement classes for label encoder
    print("🔄 Fetching placement classes from live database...")
    response = requests.get(f"{base_url}?action=get_placements", timeout=10)
    
    if response.status_code == 200:
        placement_data = response.json()
        if placement_data['success']:
            placements = placement_data['placements']
            print(f"✅ Got {len(placements)} placement classes: {placements}")
            
            # Setup label encoder with live data classes
            le = LabelEncoder()
            le.classes_ = np.array(sorted(placements))  # Convert to numpy array
            database_connected = True
            
            print("✅ Successfully connected to LIVE database via PHP bridge")
            print(f"🎯 Features: {len(feature_cols)} columns")
            print(f"🏷️ Placements: {len(placements)} classes")
        else:
            raise Exception(f"PHP bridge error: {placement_data.get('message', 'Unknown error')}")
    else:
        raise Exception(f"HTTP error: {response.status_code}")
        
except Exception as e:
    import traceback
    print(f"❌ Live database connection failed: {str(e)}")
    print("📋 Full error details:")
    print(traceback.format_exc())
    print("⚠️ Flask API will start but with limited functionality")
    database_connected = False
except Exception as e:
    print(f"❌ Database connection failed: {str(e)}")
    print("⚠️ Flask API will start but with limited functionality")
    database_connected = False
    
    # Fallback: Use hardcoded feature columns when database is unavailable
    feature_cols = [
        'CC 102', 'CC 103', 'PF 101', 'CC 104', 'IPT 101', 'IPT 102', 'CC 106', 'CC 105', 
        'WS 101', 'CAP 101', 'CAP 102', 'IM 101', 'IM 102', 'HCI 101', 'HCI 102', 'SP 101', 
        'NET 101', 'NET 102', 'IAS 101', 'IAS 102', 'soft_skill', 'communication_skill', 'technical_skill'
    ]
    
    # Create fallback label encoder with expected classes
    le = LabelEncoder()
    le.classes_ = np.array(['Business Operations', 'Research', 'Systems Development', 'Technical Support'])
    
    print(f"⚠️ Using fallback feature columns: {len(feature_cols)} features")
    print("⚠️ Using fallback label encoder with hardcoded classes")

@app.route('/health', methods=['GET'])
def health_check():
    """Health check endpoint to verify Flask API is running"""
    overall_status = "healthy" if (database_connected and model_loaded) else "degraded"
    return jsonify({
        "status": overall_status,
        "message": "Flask API is running successfully" if overall_status == "healthy" else "Flask API running with limited functionality",
        "model_loaded": model_loaded,
        "database_connected": database_connected,
        "features_count": len(feature_cols) if feature_cols else 0,
        "environment": {
            "db_host": os.environ.get('DB_HOST', 'localhost'),
            "db_user": os.environ.get('DB_USERNAME', 'root'),
            "db_name": os.environ.get('DB_NAME', 'attendancetrackernp'),
            "port": os.environ.get('PORT', '5000'),
            "render_env": 'RENDER' in os.environ
        }
    }), 200

@app.route('/predict', methods=['POST'])
def predict():
    try:
        if not model_loaded:
            return jsonify({
                "error": "Model not loaded - prediction service unavailable",
                "status": "degraded",
                "message": "Cannot make predictions without trained model"
            }), 503
            
        if not feature_cols:
            return jsonify({
                "error": "Feature columns not available - prediction service unavailable",
                "status": "degraded", 
                "message": "Cannot make predictions without feature column definition"
            }), 503
        
        data = request.json
        if not data:
            return jsonify({
                "error": "No data provided",
                "status": "error",
                "message": "Request body must contain JSON data"
            }), 400
            
        # Ensure all features are present
        features = [data.get(col, 0) for col in feature_cols]
        print("Received features:", features)
        print("Feature columns:", feature_cols)
        X = pd.DataFrame([features], columns=feature_cols)
        X = X.apply(pd.to_numeric, errors='coerce').fillna(0)
        pred = clf.predict(X)[0]
        pred_label = le.inverse_transform([pred])[0]

        # Get prediction probabilities
        proba = clf.predict_proba(X)[0]
        proba_dict = {le.inverse_transform([i])[0]: float(f'{p*100:.2f}') for i, p in enumerate(proba)}

        # Context-aware reasoning: only mention features with high grades (>85)
        importances = clf.feature_importances_

        # Extensive response template system for varied AI responses
        import random
        
        # Category mapping (should match your backend logic)
        categories = {
            'Systems Development': ['CC 102', 'CC 103', 'PF 101', 'CC 104', 'CC 106', 'CC 105', 'WS 101', 'CAP 101', 'CAP 102', 'IPT 101', 'IPT 102'],
            'Research': ['CAP 101', 'CAP 102', 'IM 101', 'IM 102', 'HCI 101', 'HCI 102'],
            'Business Operations': ['IM 101', 'IM 102', 'SP 101'],
            'Technical Support': ['NET 101', 'NET 102', 'IAS 101', 'IAS 102']
        }
        
        # Get academic performance data
        cat_subjects = categories.get(pred_label, [])
        subject_grades = [(subj, float(request.json.get(subj, 0))) for subj in cat_subjects if subj in feature_cols]
        subject_grades_sorted = sorted(subject_grades, key=lambda x: x[1], reverse=True)
        top_subjects = subject_grades_sorted[:3]
        
        # Extensive template variations for reasoning construction
        reasoning_templates = [
            # Performance-focused templates (15 variations)
            "{opener} {placement}. Academic {performance} {subjects}, {skills_summary}.",
            "{opener} {placement} based on {performance} {subjects}. {skills_summary}.",
            "Analysis {demonstrates} {placement}. {performance} in {subjects} combined with {skills_summary}.",
            "Profile evaluation {demonstrates} {placement}. Notable {performance} {subjects}, reinforced by {skills_summary}.",
            "{opener} {placement} through comprehensive assessment. {performance} in {subjects} areas, enhanced by {skills_summary}.",
            "Academic evaluation {demonstrates} {placement}. Consistent {performance} {subjects}, supported by {skills_summary}.",
            "{opener} {placement}. Strong foundation in {subjects} with {performance}, complemented by {skills_summary}.",
            "Competency analysis {demonstrates} {placement}. {performance} across {subjects} disciplines, paired with {skills_summary}.",
            "{opener} {placement} via systematic evaluation. {performance} in {subjects} coursework, enhanced by {skills_summary}.",
            "Educational assessment {demonstrates} {placement}. Demonstrated {performance} {subjects}, reinforced by {skills_summary}.",
            "{opener} {placement} through holistic analysis. Academic {performance} {subjects}, integrated with {skills_summary}.",
            "Student profile {demonstrates} {placement}. {performance} in {subjects} studies, strengthened by {skills_summary}.",
            "{opener} {placement} based on multi-dimensional evaluation. {performance} {subjects}, combined with {skills_summary}.",
            "Comprehensive analysis {demonstrates} {placement}. {performance} across {subjects} areas, enhanced by {skills_summary}.",
            "{opener} {placement}. Excellence in {subjects} with {performance}, supported by {skills_summary}."
        ]
        
        # Skill-focused reasoning variations (15 additional templates)
        skill_focused_templates = [
            "{opener} {placement}. {skills_summary} combined with academic {performance} {subjects}.",
            "Skills assessment {demonstrates} {placement}. {skills_summary}, reinforced by {performance} {subjects}.",
            "{opener} {placement} based on competency profile. {skills_summary} paired with {performance} {subjects}.",
            "Professional evaluation {demonstrates} {placement}. {skills_summary}, complemented by academic {performance} {subjects}.",
            "{opener} {placement} through skills analysis. {skills_summary} enhanced by {performance} {subjects}.",
            "Capability assessment {demonstrates} {placement}. Strong {skills_summary}, supported by {performance} {subjects}.",
            "{opener} {placement}. {skills_summary} create foundation, reinforced by {performance} {subjects}.",
            "Skills-based analysis {demonstrates} {placement}. {skills_summary} combined with academic {performance} {subjects}.",
            "{opener} {placement} via competency evaluation. {skills_summary}, enhanced by {performance} {subjects}.",
            "Professional profile {demonstrates} {placement}. {skills_summary} paired with strong {performance} {subjects}.",
            "{opener} {placement} through comprehensive skills review. {skills_summary}, complemented by {performance} {subjects}.",
            "Competency evaluation {demonstrates} {placement}. {skills_summary} reinforced by academic {performance} {subjects}.",
            "{opener} {placement} based on skills matrix. {skills_summary} combined with {performance} {subjects}.",
            "Professional assessment {demonstrates} {placement}. {skills_summary} enhanced by {performance} {subjects}.",
            "{opener} {placement}. {skills_summary} provide strong foundation, supported by {performance} {subjects}."
        ]
        
        # Combine all templates for maximum variety (30 total)
        all_templates = reasoning_templates + skill_focused_templates

        # Extensive response library for maximum variety
        import random
        
        # Expanded confidence phrases (20+ variations each)
        confidence_phrases = {
            'very_high': [
                "demonstrates exceptional alignment", "shows outstanding compatibility", "exhibits remarkable suitability", 
                "displays exceptional fit", "reveals perfect synergy", "indicates extraordinary match", "showcases ideal compatibility",
                "presents exceptional congruence", "manifests superior alignment", "exhibits outstanding resonance",
                "demonstrates remarkable harmony", "shows exceptional correlation", "displays optimal compatibility",
                "reveals extraordinary suitability", "indicates perfect calibration", "showcases superior fit",
                "presents ideal synchronization", "manifests exceptional coherence", "exhibits premium alignment",
                "demonstrates optimal positioning", "shows flawless compatibility", "displays remarkable congruence"
            ],
            'high': [
                "indicates strong suitability", "shows excellent potential", "demonstrates solid alignment", "exhibits good compatibility",
                "reveals strong correlation", "presents robust fit", "showcases notable compatibility", "indicates substantial alignment",
                "demonstrates impressive suitability", "shows strong resonance", "exhibits considerable potential",
                "presents marked compatibility", "reveals significant alignment", "indicates strong congruence",
                "demonstrates notable harmony", "shows impressive correlation", "exhibits substantial fit",
                "presents strong synchronization", "reveals considerable compatibility", "indicates impressive alignment",
                "demonstrates solid resonance", "shows notable potential", "exhibits strong coherence"
            ],
            'moderate': [
                "suggests reasonable fit", "indicates potential", "shows moderate alignment", "demonstrates adequate suitability",
                "presents fair compatibility", "reveals decent correlation", "suggests viable fit", "indicates satisfactory alignment",
                "demonstrates workable suitability", "shows acceptable potential", "exhibits reasonable compatibility",
                "presents moderate resonance", "reveals suitable alignment", "indicates fair congruence",
                "demonstrates acceptable fit", "shows reasonable harmony", "exhibits moderate correlation",
                "presents adequate compatibility", "reveals decent potential", "indicates satisfactory suitability",
                "demonstrates fair alignment", "shows moderate coherence", "exhibits reasonable positioning"
            ],
            'competitive': [
                "presents a competitive scenario", "indicates multiple viable options", "suggests versatility", "shows balanced potential",
                "reveals diverse opportunities", "presents multi-faceted compatibility", "indicates flexible alignment",
                "demonstrates adaptive suitability", "shows varied potential", "exhibits broad compatibility",
                "presents dynamic options", "reveals comprehensive fit", "indicates versatile alignment",
                "demonstrates multi-dimensional suitability", "shows diverse compatibility", "exhibits flexible potential",
                "presents balanced opportunities", "reveals adaptive fit", "indicates comprehensive suitability",
                "demonstrates varied alignment", "shows multi-faceted potential", "exhibits dynamic compatibility"
            ]
        }
        
        # Extensive opening phrases for reasoning (30+ variations)
        reasoning_openers = [
            "Recommended for", "Ideally suited for", "Best positioned for", "Optimally aligned with",
            "Strategically matched to", "Perfectly calibrated for", "Exceptionally suited for", "Strongly recommended for",
            "Thoughtfully aligned with", "Carefully matched to", "Precisely positioned for", "Expertly suited for",
            "Intelligently assigned to", "Skillfully matched with", "Purposefully aligned for", "Methodically suited for",
            "Analytically positioned in", "Systematically matched to", "Logically assigned for", "Scientifically suited for",
            "Empirically aligned with", "Data-driven recommendation for", "Evidence-based assignment to", "Algorithmically matched with",
            "Computationally optimized for", "Predictively aligned with", "Statistically suited for", "Quantitatively positioned in",
            "Assessment-based recommendation for", "Profile-matched assignment to", "Competency-aligned placement in", "Skills-based positioning for"
        ]
        
        # Template variables for reasoning construction
        soft_skill = float(request.json.get('soft_skill', 0))
        comm_skill = float(request.json.get('communication_skill', 0))
        tech_skill = float(request.json.get('technical_skill', 0))
        
        # Generate template variables
        opener_options = random.choice(reasoning_openers)
        
        # Subject performance description
        if top_subjects:
            subjects_text = ', '.join([f"{name.replace('_', ' ')}" for name, value in top_subjects])
            avg_grade = sum([value for name, value in top_subjects]) / len(top_subjects)
            if avg_grade >= 90:
                performance = random.choice(["exceptional performance in", "outstanding achievement in", "superior results in", "exemplary grades in"])
            elif avg_grade >= 85:
                performance = random.choice(["excellent results in", "strong performance in", "impressive achievement in", "notable success in"])
            elif avg_grade >= 80:
                performance = random.choice(["solid performance in", "commendable results in", "competent achievement in", "respectable grades in"])
            else:
                performance = random.choice(["adequate results in", "satisfactory performance in", "sufficient achievement in", "basic competency in"])
        else:
            subjects_text = f"{pred_label.lower()} related subjects"
            performance = random.choice(["demonstrated capability in", "established foundation in", "proven competence in"])
        
        # Skills summary generation with extensive variety
        skills_data = {
            'soft': {'value': soft_skill, 'descriptors': [
                'interpersonal excellence', 'professional maturity', 'collaborative aptitude', 'leadership potential',
                'adaptability skills', 'problem-solving abilities', 'critical thinking', 'emotional intelligence',
                'work ethic strength', 'team collaboration', 'time management', 'organizational skills',
                'initiative taking', 'responsibility acceptance', 'stress management', 'conflict resolution'
            ]},
            'comm': {'value': comm_skill, 'descriptors': [
                'communication proficiency', 'verbal articulation', 'presentation skills', 'written communication',
                'listening abilities', 'interpersonal communication', 'public speaking', 'documentation skills',
                'clarity of expression', 'persuasive communication', 'cross-cultural communication', 'digital communication',
                'meeting facilitation', 'negotiation skills', 'customer service', 'stakeholder engagement'
            ]},
            'tech': {'value': tech_skill, 'descriptors': [
                'technical competency', 'analytical thinking', 'system understanding', 'troubleshooting skills',
                'digital literacy', 'technical problem-solving', 'software proficiency', 'data analysis',
                'technical learning', 'innovation mindset', 'process optimization', 'quality assurance',
                'technical documentation', 'system integration', 'performance optimization', 'security awareness'
            ]}
        }
        
        # Categorize skills with varied descriptors
        high_skills = [(k, random.choice(v['descriptors'])) for k, v in skills_data.items() if v['value'] >= 4.5]
        good_skills = [(k, random.choice(v['descriptors'])) for k, v in skills_data.items() if 4.0 <= v['value'] < 4.5]
        decent_skills = [(k, random.choice(v['descriptors'])) for k, v in skills_data.items() if 3.5 <= v['value'] < 4.0]
        
        # Generate skills summary with extensive variations
        skill_summary_options = []
        
        if len(high_skills) >= 3:
            skill_summary_options.extend([
                f"exceptional {', '.join([desc for _, desc in high_skills[:-1]])} and {high_skills[-1][1]}",
                f"outstanding proficiency in {', '.join([desc for _, desc in high_skills])}",
                f"superior {high_skills[0][1]}, {high_skills[1][1]}, and {high_skills[2][1]}",
                f"comprehensive mastery of {', '.join([desc for _, desc in high_skills])}"
            ])
        elif len(high_skills) == 2:
            if good_skills:
                skill_summary_options.extend([
                    f"strong {high_skills[0][1]} and {high_skills[1][1]}, complemented by solid {good_skills[0][1]}",
                    f"excellent {' and '.join([desc for _, desc in high_skills])}, supported by competent {good_skills[0][1]}",
                    f"impressive {high_skills[0][1]} paired with notable {high_skills[1][1]} and adequate {good_skills[0][1]}"
                ])
            else:
                skill_summary_options.extend([
                    f"strong {high_skills[0][1]} and {high_skills[1][1]}",
                    f"excellent {' and '.join([desc for _, desc in high_skills])}",
                    f"impressive dual strengths in {high_skills[0][1]} and {high_skills[1][1]}"
                ])
        elif len(high_skills) == 1:
            if good_skills:
                skill_summary_options.extend([
                    f"outstanding {high_skills[0][1]} complemented by solid {' and '.join([desc for _, desc in good_skills])}",
                    f"exceptional {high_skills[0][1]} supported by competent {good_skills[0][1]}",
                    f"superior {high_skills[0][1]} paired with adequate {' and '.join([desc for _, desc in good_skills])}"
                ])
            else:
                skill_summary_options.extend([
                    f"notable {high_skills[0][1]}",
                    f"strong {high_skills[0][1]}",
                    f"impressive {high_skills[0][1]}"
                ])
        elif len(good_skills) >= 2:
            skill_summary_options.extend([
                f"balanced {' and '.join([desc for _, desc in good_skills])}",
                f"solid foundation in {', '.join([desc for _, desc in good_skills])}",
                f"competent {good_skills[0][1]} and {good_skills[1][1]}",
                f"well-rounded {' and '.join([desc for _, desc in good_skills])}"
            ])
        elif len(good_skills) == 1:
            if decent_skills:
                skill_summary_options.extend([
                    f"solid {good_skills[0][1]} with developing {decent_skills[0][1]}",
                    f"competent {good_skills[0][1]} and emerging {decent_skills[0][1]}",
                    f"established {good_skills[0][1]} paired with growing {decent_skills[0][1]}"
                ])
            else:
                skill_summary_options.extend([
                    f"competent {good_skills[0][1]}",
                    f"solid {good_skills[0][1]}",
                    f"adequate {good_skills[0][1]}"
                ])
        else:
            skill_summary_options.extend([
                "developing professional capabilities",
                "emerging skill foundation",
                "growing competency base",
                "evolving professional skills"
            ])
        
        skills_summary = random.choice(skill_summary_options)
        
        # Construct reasoning using template system
        selected_template = random.choice(all_templates)
        reasoning = selected_template.format(
            opener=opener_options,
            placement=pred_label,
            performance=performance,
            subjects=subjects_text,
            skills_summary=skills_summary,
            demonstrates=random.choice(confidence_phrases['high'][:3])  # Use subset for variety
        )
        
        # Enhanced placement-specific insights with extensive variety
        placement_insights = {
            'Systems Development': {
                'tech': [
                    "Strong technical competencies will accelerate programming language acquisition and system architecture understanding.",
                    "Technical proficiency provides excellent foundation for software development methodologies and coding practices.",
                    "Advanced technical skills will facilitate rapid adaptation to development frameworks and programming paradigms.",
                    "Technical expertise will enhance debugging capabilities and code optimization techniques.",
                    "Strong technical foundation supports learning of database design and system integration concepts."
                ],
                'comm': [
                    "Communication abilities will prove invaluable for client requirement gathering and stakeholder presentations.",
                    "Strong communication skills facilitate effective team collaboration and code review processes.",
                    "Communication proficiency enhances user story development and technical documentation creation.",
                    "Excellent communication supports agile methodology participation and sprint planning activities.",
                    "Communication skills will aid in explaining technical concepts to non-technical stakeholders."
                ],
                'soft': [
                    "Soft skills will enhance project management capabilities and cross-functional team collaboration.",
                    "Strong interpersonal abilities support peer programming and mentorship opportunities.",
                    "Soft skills facilitate problem-solving approaches and creative solution development.",
                    "Professional maturity will improve time management and deadline adherence in development cycles.",
                    "Leadership qualities will support technical team coordination and junior developer guidance."
                ]
            },
            'Technical Support': {
                'tech': [
                    "Technical competencies provide robust foundation for system troubleshooting and problem diagnosis.",
                    "Strong technical skills will accelerate learning of network protocols and system administration.",
                    "Technical proficiency supports hardware/software integration and performance optimization tasks.",
                    "Advanced technical understanding enhances incident resolution and root cause analysis abilities.",
                    "Technical expertise will facilitate knowledge base development and solution documentation."
                ],
                'comm': [
                    "Communication skills are essential for effective customer service and technical issue explanation.",
                    "Strong verbal abilities will improve user training and support ticket resolution efficiency.",
                    "Communication proficiency enhances team coordination and escalation management processes.",
                    "Excellent interpersonal skills support customer relationship building and satisfaction improvement.",
                    "Communication abilities facilitate clear documentation and knowledge transfer activities."
                ],
                'soft': [
                    "Soft skills will enhance patience and empathy when assisting frustrated users and clients.",
                    "Strong interpersonal abilities improve customer satisfaction and repeat service requests.",
                    "Professional maturity supports stress management during high-pressure support situations.",
                    "Organizational skills will enhance ticket prioritization and workflow management efficiency.",
                    "Adaptability will prove valuable when learning new support tools and technologies."
                ]
            },
            'Business Operations': {
                'soft': [
                    "Soft skills are fundamental for stakeholder management and organizational process improvement.",
                    "Strong interpersonal abilities enhance cross-departmental collaboration and project coordination.",
                    "Leadership qualities will support team management and operational efficiency initiatives.",
                    "Professional maturity facilitates strategic planning and business objective alignment.",
                    "Organizational skills will improve workflow optimization and resource allocation decisions."
                ],
                'comm': [
                    "Communication abilities will drive success in reporting, presentations, and stakeholder engagement.",
                    "Strong verbal and written skills enhance meeting facilitation and documentation processes.",
                    "Communication proficiency supports negotiation and vendor relationship management activities.",
                    "Excellent presentation abilities will improve executive reporting and proposal development.",
                    "Interpersonal skills facilitate change management and organizational development initiatives."
                ],
                'tech': [
                    "Technical skills will support process automation and digital transformation initiatives.",
                    "Technology proficiency enhances data analysis and business intelligence reporting capabilities.",
                    "Technical understanding supports system implementation and workflow optimization projects.",
                    "Digital literacy will improve productivity tool utilization and process digitization efforts.",
                    "Technical competency facilitates vendor evaluation and technology adoption decisions."
                ]
            },
            'Research': {
                'tech': [
                    "Technical competencies will enhance data collection methodologies and analysis tool proficiency.",
                    "Strong analytical skills support statistical analysis and research methodology development.",
                    "Technical proficiency facilitates database management and research software utilization.",
                    "Advanced technical abilities enhance literature review efficiency and data visualization creation.",
                    "Technology skills will support survey development and quantitative analysis capabilities."
                ],
                'comm': [
                    "Communication skills are vital for research presentation and academic collaboration success.",
                    "Strong writing abilities enhance research publication and grant proposal development.",
                    "Communication proficiency supports conference presentations and peer review processes.",
                    "Excellent verbal skills facilitate research interviews and focus group moderation.",
                    "Interpersonal abilities enhance collaborative research and cross-disciplinary partnerships."
                ],
                'soft': [
                    "Soft skills will improve research planning, time management, and project completion rates.",
                    "Strong organizational abilities enhance literature management and research workflow efficiency.",
                    "Professional maturity supports independent research and self-directed learning initiatives.",
                    "Critical thinking skills facilitate hypothesis development and methodology design.",
                    "Persistence and adaptability will prove valuable during challenging research phases."
                ]
            }
        }
        
        # Generate supporting message with varied insights
        support_messages = []
        
        # Add skill-specific insights based on high performance areas
        for skill_type, skill_value in [('tech', tech_skill), ('comm', comm_skill), ('soft', soft_skill)]:
            if skill_value >= 4.0 and pred_label in placement_insights:
                if skill_type in placement_insights[pred_label]:
                    insight = random.choice(placement_insights[pred_label][skill_type])
                    support_messages.append(insight)
        
        # If no high skills, add general placement benefits
        if not support_messages:
            general_benefits = {
                'Systems Development': [
                    "This placement offers excellent opportunities for technical skill development and creative problem-solving.",
                    "The role provides strong foundation for software engineering career advancement and technology innovation.",
                    "Development experience will build valuable programming expertise and system design capabilities."
                ],
                'Technical Support': [
                    "This placement develops crucial technical troubleshooting and customer service competencies.",
                    "The role builds comprehensive IT support skills and professional communication abilities.",
                    "Support experience provides excellent foundation for advanced technical career progression."
                ],
                'Business Operations': [
                    "This placement offers valuable exposure to organizational processes and business strategy development.",
                    "The role develops essential project management and stakeholder coordination capabilities.",
                    "Operations experience builds critical business acumen and professional leadership skills."
                ],
                'Research': [
                    "This placement provides excellent foundation for analytical thinking and academic methodology mastery.",
                    "The role develops crucial research skills and scientific inquiry capabilities.",
                    "Research experience builds valuable data analysis and critical evaluation competencies."
                ]
            }
            if pred_label in general_benefits:
                support_messages.append(random.choice(general_benefits[pred_label]))
        
        # Construct final reasoning
        if support_messages:
            # Randomly select 1-2 support messages to avoid overly long responses
            selected_messages = random.sample(support_messages, min(len(support_messages), random.choice([1, 2])))
            full_reasoning = reasoning + " " + " ".join(selected_messages)
        else:
            full_reasoning = reasoning

        # Add probability explanation
        sorted_probs = sorted(proba_dict.items(), key=lambda x: x[1], reverse=True)
        top_label, top_prob = sorted_probs[0]
        second_label, second_prob = sorted_probs[1]
        third_label, third_prob = sorted_probs[2]
        lowest_label, lowest_prob = sorted_probs[3]
        
        # Extensive probability explanation templates with maximum variety
        probability_templates = {
            'very_high': [
                "The analysis {phrase} with {top_label} ({top_prob:.0f}% confidence). The substantial margin over other placements ({second_label}: {second_prob:.0f}%, {third_label}: {third_prob:.0f}%) indicates exceptional alignment with this career path.",
                "Predictive modeling {phrase} for {top_label} at {top_prob:.0f}% confidence. The significant gap from secondary options ({second_label}: {second_prob:.0f}%, {third_label}: {third_prob:.0f}%) confirms optimal career positioning.",
                "Assessment results {phrase} with {top_label} ({top_prob:.0f}% probability). The commanding lead over alternatives ({second_label}: {second_prob:.0f}%, {third_label}: {third_prob:.0f}%) validates this placement recommendation.",
                "Statistical analysis {phrase} for {top_label} with {top_prob:.0f}% confidence. The decisive margin above competing placements ({second_label}: {second_prob:.0f}%, {third_label}: {third_prob:.0f}%) supports this career direction.",
                "Comprehensive evaluation {phrase} with {top_label} at {top_prob:.0f}% likelihood. The substantial advantage over other paths ({second_label}: {second_prob:.0f}%, {third_label}: {third_prob:.0f}%) confirms strategic career alignment."
            ],
            'high': [
                "The student {phrase} for {top_label} with {top_prob:.0f}% confidence. While {second_label} ({second_prob:.0f}%) shows promise, the profile more strongly aligns with {top_label} opportunities.",
                "Analysis {phrase} for {top_label} at {top_prob:.0f}% probability. Although {second_label} ({second_prob:.0f}%) presents viability, {top_label} better matches the competency profile.",
                "Evaluation {phrase} for {top_label} with {top_prob:.0f}% confidence. Despite {second_label}'s {second_prob:.0f}% compatibility, {top_label} offers superior alignment with demonstrated capabilities.",
                "Assessment {phrase} for {top_label} at {top_prob:.0f}% likelihood. While {second_label} ({second_prob:.0f}%) remains viable, {top_label} provides optimal career trajectory matching.",
                "Predictive modeling {phrase} for {top_label} with {top_prob:.0f}% confidence. Though {second_label} ({second_prob:.0f}%) shows potential, {top_label} delivers stronger competency alignment."
            ],
            'competitive': [
                "This analysis {phrase} between {top_label} ({top_prob:.0f}%) and {second_label} ({second_prob:.0f}%). The close probabilities indicate versatility and potential for success in multiple placements.",
                "Assessment results {phrase} for both {top_label} ({top_prob:.0f}%) and {second_label} ({second_prob:.0f}%). This competitive scenario suggests adaptable capabilities across career paths.",
                "Evaluation {phrase} between {top_label} ({top_prob:.0f}%) and {second_label} ({second_prob:.0f}%). The narrow margin reflects a well-rounded profile suitable for diverse opportunities.",
                "Statistical modeling {phrase} for {top_label} ({top_prob:.0f}%) and {second_label} ({second_prob:.0f}%). This balanced outcome indicates flexible career potential and adaptive competencies.",
                "Predictive analysis {phrase} between {top_label} ({top_prob:.0f}%) and {second_label} ({second_prob:.0f}%). The competitive distribution suggests multi-dimensional capabilities and career flexibility."
            ],
            'moderate': [
                "The student {phrase} for {top_label} ({top_prob:.0f}% confidence), with notable compatibility for {second_label} ({second_prob:.0f}%) and {third_label} ({third_prob:.0f}%). This profile suggests adaptability across multiple domains.",
                "Analysis {phrase} for {top_label} at {top_prob:.0f}% likelihood, while maintaining compatibility with {second_label} ({second_prob:.0f}%) and {third_label} ({third_prob:.0f}%). This indicates versatile professional potential.",
                "Assessment {phrase} for {top_label} with {top_prob:.0f}% confidence, complemented by viability in {second_label} ({second_prob:.0f}%) and {third_label} ({third_prob:.0f}%). This reflects broad-based competency development.",
                "Evaluation {phrase} for {top_label} ({top_prob:.0f}% probability), supported by secondary options in {second_label} ({second_prob:.0f}%) and {third_label} ({third_prob:.0f}%). This suggests multi-faceted career readiness.",
                "Predictive modeling {phrase} for {top_label} at {top_prob:.0f}% confidence, with substantial backing from {second_label} ({second_prob:.0f}%) and {third_label} ({third_prob:.0f}%). This indicates comprehensive skill development."
            ],
            'balanced': [
                "The analysis reveals versatile capabilities with {top_label} leading at {top_prob:.0f}%. The balanced distribution ({second_label}: {second_prob:.0f}%, {third_label}: {third_prob:.0f}%, {lowest_label}: {lowest_prob:.0f}%) suggests exceptional adaptability across OJT environments.",
                "Assessment indicates comprehensive competencies with {top_label} at {top_prob:.0f}%. The distributed probabilities ({second_label}: {second_prob:.0f}%, {third_label}: {third_prob:.0f}%, {lowest_label}: {lowest_prob:.0f}%) reflect multi-domain professional potential.",
                "Evaluation demonstrates broad capabilities with {top_label} preferred at {top_prob:.0f}%. The even distribution across categories ({second_label}: {second_prob:.0f}%, {third_label}: {third_prob:.0f}%, {lowest_label}: {lowest_prob:.0f}%) indicates diverse career readiness.",
                "Analysis showcases versatile profile with {top_label} leading at {top_prob:.0f}%. The spread across all areas ({second_label}: {second_prob:.0f}%, {third_label}: {third_prob:.0f}%, {lowest_label}: {lowest_prob:.0f}%) suggests flexible career positioning.",
                "Predictive modeling reveals adaptable competencies with {top_label} at {top_prob:.0f}%. The balanced probabilities ({second_label}: {second_prob:.0f}%, {third_label}: {third_prob:.0f}%, {lowest_label}: {lowest_prob:.0f}%) indicate cross-functional potential."
            ],
            'standard': [
                "Predictive modeling identifies {top_label} as optimal placement ({top_prob:.0f}% confidence) based on academic performance and skill assessment. Secondary options include {second_label} ({second_prob:.0f}%) and {third_label} ({third_prob:.0f}%).",
                "Statistical analysis recommends {top_label} with {top_prob:.0f}% confidence through comprehensive evaluation. Alternative pathways show {second_label} ({second_prob:.0f}%) and {third_label} ({third_prob:.0f}%) compatibility.",
                "Assessment algorithm suggests {top_label} at {top_prob:.0f}% probability based on holistic profile review. Supplementary options demonstrate {second_label} ({second_prob:.0f}%) and {third_label} ({third_prob:.0f}%) alignment.",
                "Evaluation system indicates {top_label} as preferred placement ({top_prob:.0f}% likelihood) through multi-factor analysis. Additional considerations include {second_label} ({second_prob:.0f}%) and {third_label} ({third_prob:.0f}%).",
                "Analytical framework recommends {top_label} with {top_prob:.0f}% confidence via comprehensive assessment. Backup options present {second_label} ({second_prob:.0f}%) and {third_label} ({third_prob:.0f}%) viability."
            ]
        }
        
        # Determine probability explanation category and select template
        prob_explanation = ""
        
        if top_prob >= 60:
            phrase = random.choice(confidence_phrases['very_high'])
            template = random.choice(probability_templates['very_high'])
            prob_explanation = template.format(
                phrase=phrase, top_label=top_label, top_prob=top_prob,
                second_label=second_label, second_prob=second_prob,
                third_label=third_label, third_prob=third_prob
            )
        elif top_prob >= 45:
            phrase = random.choice(confidence_phrases['high'])
            template = random.choice(probability_templates['high'])
            prob_explanation = template.format(
                phrase=phrase, top_label=top_label, top_prob=top_prob,
                second_label=second_label, second_prob=second_prob
            )
        elif top_prob - second_prob <= 8:
            phrase = random.choice(confidence_phrases['competitive'])
            template = random.choice(probability_templates['competitive'])
            prob_explanation = template.format(
                phrase=phrase, top_label=top_label, top_prob=top_prob,
                second_label=second_label, second_prob=second_prob
            )
        elif top_prob >= 35 and second_prob >= 20:
            phrase = random.choice(confidence_phrases['moderate'])
            template = random.choice(probability_templates['moderate'])
            prob_explanation = template.format(
                phrase=phrase, top_label=top_label, top_prob=top_prob,
                second_label=second_label, second_prob=second_prob,
                third_label=third_label, third_prob=third_prob
            )
        elif min(proba_dict.values()) > 15:
            template = random.choice(probability_templates['balanced'])
            prob_explanation = template.format(
                top_label=top_label, top_prob=top_prob,
                second_label=second_label, second_prob=second_prob,
                third_label=third_label, third_prob=third_prob,
                lowest_label=lowest_label, lowest_prob=lowest_prob
            )
        else:
            template = random.choice(probability_templates['standard'])
            prob_explanation = template.format(
                top_label=top_label, top_prob=top_prob,
                second_label=second_label, second_prob=second_prob,
                third_label=third_label, third_prob=third_prob
            )


        return jsonify({'placement': pred_label, 'reasoning': full_reasoning, 'probabilities': proba_dict, 'prob_explanation': prob_explanation})
    
    except Exception as e:
        import traceback
        print(f"❌ Prediction error: {str(e)}")
        print("📋 Full error traceback:")
        print(traceback.format_exc())
        return jsonify({
            "error": "Prediction failed",
            "status": "error",
            "message": str(e),
            "details": "Check Flask console for full error details"
        }), 500


# --- Post-Analysis Sample Output Endpoint ---
@app.route('/post_analysis', methods=['GET'])
def post_analysis():
    import random
    
    # Enhanced job roles library with extensive variety
    job_roles = {
        'Systems Development': [
            "Software Developer", "Web Developer", "Application Programmer", "Systems Analyst", 
            "Full-Stack Developer", "Frontend Developer", "Backend Developer", "Mobile App Developer",
            "DevOps Engineer", "Software Engineer", "Database Developer", "API Developer",
            "Cloud Developer", "Solutions Architect", "Technical Lead", "Senior Developer",
            "UI/UX Developer", "Game Developer", "Embedded Systems Developer", "Quality Assurance Engineer"
        ],
        'Research': [
            "Research Assistant", "Data Analyst", "Academic Researcher", "Market Researcher",
            "Data Scientist", "Research Coordinator", "Statistical Analyst", "Business Intelligence Analyst",
            "Quantitative Researcher", "Survey Researcher", "User Experience Researcher", "Policy Analyst",
            "Research Specialist", "Data Research Analyst", "Market Intelligence Analyst", "Competitive Intelligence Analyst",
            "Social Media Researcher", "Consumer Insights Analyst", "Research Consultant", "Trend Analyst"
        ],
        'Business Operations': [
            "Business Analyst", "Operations Coordinator", "Administrative Assistant", "Project Coordinator",
            "Operations Manager", "Business Process Analyst", "Project Manager", "Program Coordinator",
            "Executive Assistant", "Operations Specialist", "Business Development Associate", "Strategy Analyst",
            "Process Improvement Specialist", "Management Trainee", "Operations Analyst", "Corporate Coordinator",
            "Business Operations Specialist", "Administrative Coordinator", "Office Manager", "Business Support Specialist"
        ],
        'Technical Support': [
            "IT Support Specialist", "Help Desk Technician", "Network Support", "Technical Support Representative",
            "System Administrator", "Desktop Support Technician", "Network Administrator", "IT Support Analyst",
            "Technical Support Engineer", "Customer Support Specialist", "Field Service Technician", "IT Consultant",
            "Hardware Technician", "Software Support Specialist", "Infrastructure Support", "Security Support Analyst",
            "Cloud Support Engineer", "Database Administrator", "Technical Account Manager", "IT Service Desk Analyst"
        ]
    }
    
    # Enhanced analysis templates library
    analysis_templates = {
        'probability_high_confidence': [
            "The predictive model demonstrates exceptional confidence ({top_prob}%) in recommending {top_label} as the optimal placement. This substantial margin over alternative placements indicates a clear academic and skills alignment.",
            "With {top_prob}% confidence, the algorithm identifies {top_label} as the ideal career trajectory. The significant statistical gap from other options validates this recommendation.",
            "Statistical analysis reveals {top_prob}% probability favoring {top_label} placement. This high confidence level suggests excellent compatibility between student profile and career requirements.",
            "The AI assessment shows {top_prob}% confidence in {top_label} as the premier placement option. This decisive margin reflects strong academic-career correlation.",
            "Predictive modeling indicates {top_prob}% likelihood for {top_label} success. The commanding statistical lead demonstrates clear suitability for this career path."
        ],
        'probability_competitive': [
            "The analysis reveals competitive potential between {top_label} ({top_prob}%) and {second_label} ({second_prob}%). This close margin suggests versatile capabilities and multi-pathway career readiness.",
            "Statistical modeling indicates balanced suitability for both {top_label} ({top_prob}%) and {second_label} ({second_prob}%). Such competitive scoring reflects adaptable professional competencies.",
            "The assessment identifies dual-pathway potential with {top_label} at {top_prob}% and {second_label} at {second_prob}%. This competitive scenario suggests flexible career positioning.",
            "Predictive results show {top_label} ({top_prob}%) narrowly leading {second_label} ({second_prob}%). The tight competition indicates broad-based professional capabilities.",
            "Analysis demonstrates comparable alignment for {top_label} ({top_prob}%) and {second_label} ({second_prob}%). This balanced outcome reflects multi-dimensional career potential."
        ],
        'probability_moderate': [
            "The model identifies {top_label} as the preferred placement with {top_prob}% confidence, while maintaining recognition of secondary strengths in other areas.",
            "Predictive analysis favors {top_label} at {top_prob}% probability, with notable compatibility across multiple career domains indicating versatile professional readiness.",
            "Statistical assessment recommends {top_label} with {top_prob}% confidence, supported by distributed competencies that suggest adaptable career potential.",
            "The algorithm suggests {top_label} as the optimal path ({top_prob}% confidence) while acknowledging multi-faceted capabilities across various professional domains.",
            "Assessment results favor {top_label} at {top_prob}% likelihood, complemented by balanced competencies that indicate flexible career positioning."
        ]
    }
    # strengths_section will be set after sup_highest/self_highest are defined
    # supervisor_comment will be set after data is loaded
    # Helper for Likert label
    def likert_label(val):
        if val is None:
            return '-'
        try:
            v = float(val)
        except Exception:
            return '-'
        if v >= 4.75:
            return 'Excellent'
        elif v >= 3.75:
            return 'Very Good'
        elif v >= 2.75:
            return 'Good'
        elif v >= 1.75:
            return 'Fair'
        else:
            return 'Poor'
    
    import requests
    
    student_id = request.args.get('student_id')
    if not student_id:
        return jsonify({'error': 'student_id is required'}), 400

    # Use PHP bridge to fetch pre-assessment data
    debug_info = {
        'original_student_id': student_id,
        'attempts': [],
        'query': f"SELECT * FROM pre_assessment WHERE STUDENT_ID = {student_id}",
        'table': 'pre_assessment'
    }

    try:
        # Debug environment detection
        is_render = 'RENDER' in os.environ or 'RENDER_SERVICE_ID' in os.environ
        print(f"🔍 Environment check - RENDER: {'RENDER' in os.environ}, RENDER_SERVICE_ID: {'RENDER_SERVICE_ID' in os.environ}")
        print(f"🔍 Is Render environment: {is_render}")
        
        # Fetch pre-assessment data using PHP bridge with environment-aware URL
        if is_render:
            # Production environment (Render)
            base_url = 'https://internconnect-kjzb.onrender.com/api/database_bridge.php'
            print(f"🚀 Production mode detected - using URL: {base_url}")
        else:
            # Local development environment - use the same logic as initialization
            possible_bases = [
                'http://localhost:80/InternConnect/api/database_bridge.php',
                'http://localhost/InternConnect/api/database_bridge.php',
                'http://127.0.0.1:80/InternConnect/api/database_bridge.php',
                'http://127.0.0.1/InternConnect/api/database_bridge.php'
            ]
            
            base_url = None
            for test_url in possible_bases:
                try:
                    test_response = requests.get(test_url + '?action=test', timeout=3)
                    if test_response.status_code == 200:
                        base_url = test_url
                        break
                except:
                    continue
            
            if not base_url:
                base_url = 'http://localhost/InternConnect/api/database_bridge.php'  # fallback
        
        full_url = f"{base_url}?action=get_pre_assessment&student_id={student_id}"
        print(f"🔗 Making request to: {full_url}")
        
        try:
            response = requests.get(full_url, timeout=10)
        except requests.exceptions.ConnectionError as e:
            debug_info['attempts'].append({'connection_error': str(e)})
            return jsonify({'error': 'Cannot connect to database bridge', 'debug': debug_info}), 500
        except requests.exceptions.Timeout as e:
            debug_info['attempts'].append({'timeout_error': str(e)})
            return jsonify({'error': 'Database bridge request timeout', 'debug': debug_info}), 500
        except Exception as e:
            debug_info['attempts'].append({'request_error': str(e)})
            return jsonify({'error': 'Request error to database bridge', 'debug': debug_info}), 500
        
        debug_info['attempts'].append({
            'php_bridge_status': response.status_code,
            'url_used': full_url,
            'environment': 'RENDER' if 'RENDER' in os.environ else 'LOCAL'
        })
        
        if response.status_code != 200:
            error_msg = f"HTTP {response.status_code} from database bridge"
            try:
                error_detail = response.text[:500]  # First 500 chars of error
                debug_info['attempts'].append({'error_response': error_detail})
            except:
                pass
            return jsonify({'error': error_msg, 'debug': debug_info}), 500
            
        result = response.json()
        debug_info['attempts'].append({'php_bridge_response': 'success'})
        
        if not result.get('success', False):
            return jsonify({'error': 'Student not found', 'debug': debug_info}), 404
            
        data = result.get('data', {})
        if not data:
            return jsonify({'error': 'No student data found', 'debug': debug_info}), 404
            
    except requests.RequestException as e:
        debug_info['attempts'].append({'php_bridge_error': str(e)})
        return jsonify({'error': 'Database connection failed', 'debug': debug_info}), 500
    except Exception as e:
        debug_info['attempts'].append({'general_error': str(e)})
        return jsonify({'error': 'Failed to process student data', 'debug': debug_info}), 500

    # Placement and reasoning
    placement = data.get('ojt_placement')
    reasoning = data.get('prediction_reasoning')
    supervisor_comment = data.get('supervisor_comment')

    # --- Prepare for analysis: get predicted and highest categories early ---
    post_categories = {
        'Systems Development': (data.get('post_systems_development_avg'), data.get('self_systems_development_avg')),
        'Research': (data.get('post_research_avg'), data.get('self_research_avg')),
        'Business Operations': (data.get('post_business_operations_avg'), data.get('self_business_operations_avg')),
        'Technical Support': (data.get('post_technical_support_avg'), data.get('self_technical_support_avg'))
    }
    sup_highest = max(post_categories.items(), key=lambda x: (x[1][0] if x[1][0] is not None else -1))
    self_highest = max(post_categories.items(), key=lambda x: (x[1][1] if x[1][1] is not None else -1))
    predicted = data.get('ojt_placement')
    # Probabilities (stored as JSON string)
    import json
    try:
        probabilities = json.loads(data.get('prediction_probabilities') or '{}')
    except Exception:
        probabilities = {}

    # Post-assessment averages
    post_assessment_averages = [
        {
            'category': 'Systems Development',
            'supervisor_avg': data.get('post_systems_development_avg'),
            'self_avg': data.get('self_systems_development_avg')
        },
        {
            'category': 'Research',
            'supervisor_avg': data.get('post_research_avg'),
            'self_avg': data.get('self_research_avg')
        },
        {
            'category': 'Business Operations',
            'supervisor_avg': data.get('post_business_operations_avg'),
            'self_avg': data.get('self_business_operations_avg')
        },
        {
            'category': 'Technical Support',
            'supervisor_avg': data.get('post_technical_support_avg'),
            'self_avg': data.get('self_technical_support_avg')
        }
    ]

    # STRICT VALIDATION: Only allow post-analysis when complete post-assessment data exists
    # Post-analysis requires actual supervisor and self ratings from the OJT experience
    post_supervisor_ratings = [
        data.get('post_systems_development_avg'),
        data.get('post_research_avg'),
        data.get('post_business_operations_avg'),
        data.get('post_technical_support_avg')
    ]
    
    post_self_ratings = [
        data.get('self_systems_development_avg'),
        data.get('self_research_avg'),
        data.get('self_business_operations_avg'),
        data.get('self_technical_support_avg')
    ]
    
    # Filter out None/empty values
    valid_supervisor_ratings = [r for r in post_supervisor_ratings if r is not None and r != '' and r != 0]
    valid_self_ratings = [r for r in post_self_ratings if r is not None and r != '' and r != 0]
    
    # Require substantial post-assessment data for meaningful analysis
    # Need at least 3 supervisor ratings AND 3 self ratings
    min_required_ratings = 3
    
    if len(valid_supervisor_ratings) < min_required_ratings or len(valid_self_ratings) < min_required_ratings:
        return jsonify({
            'error': 'Post-analysis requires completed post-assessment data. Student must have supervisor and self-evaluation ratings from their OJT experience.',
            'debug': {
                'supervisor_ratings_count': len(valid_supervisor_ratings),
                'self_ratings_count': len(valid_self_ratings),
                'required_minimum': min_required_ratings,
                'supervisor_ratings': valid_supervisor_ratings,
                'self_ratings': valid_self_ratings,
                'student_id': student_id
            }
        }), 404

    # Enhanced probability explanation with extensive variety
    prob_explanation = ''
    if probabilities:
        sorted_probs = sorted(probabilities.items(), key=lambda x: x[1], reverse=True)
        if len(sorted_probs) >= 2:
            top_label, top_prob = sorted_probs[0]
            second_label, second_prob = sorted_probs[1]
            
            if float(top_prob) >= 60:
                prob_explanation = random.choice(analysis_templates['probability_high_confidence']).format(
                    top_prob=top_prob, top_label=top_label
                )
            elif float(top_prob) - float(second_prob) < 10:
                prob_explanation = random.choice(analysis_templates['probability_competitive']).format(
                    top_label=top_label, top_prob=top_prob, 
                    second_label=second_label, second_prob=second_prob
                )
            else:
                prob_explanation = random.choice(analysis_templates['probability_moderate']).format(
                    top_label=top_label, top_prob=top_prob
                )

    # --- Correlation Analysis (must be set before conclusion logic) ---
    correlation_analysis = ""
    pred_sup = post_categories.get(predicted, (None, None))[0]
    pred_self = post_categories.get(predicted, (None, None))[1]
    if pred_sup is not None and pred_self is not None:
        avg_other_sup = [v[0] for k, v in post_categories.items() if k != predicted and v[0] is not None]
        avg_other_self = [v[1] for k, v in post_categories.items() if k != predicted and v[1] is not None]
        if avg_other_sup and avg_other_self:
            sup_corr = pred_sup - (sum(avg_other_sup) / len(avg_other_sup))
            self_corr = pred_self - (sum(avg_other_self) / len(avg_other_self))
            sup_label = likert_label(pred_sup)
            self_label = likert_label(pred_self)
            # Enhanced supervisor message variations
            sup_equal_msgs = [
                f"Supervisor evaluation rated the predicted category as {sup_label}, maintaining consistency with other assessed areas.",
                f"The supervisor's {sup_label} rating for the predicted placement aligns with their overall assessment pattern.",
                f"Supervisor feedback indicates {sup_label} performance in the predicted area, comparable to other evaluated domains.",
                f"The supervisor assigned a {sup_label} rating to the predicted category, reflecting balanced performance across areas.",
                f"Supervisor assessment shows {sup_label} competency in the predicted placement, consistent with cross-domain evaluation.",
                f"The supervisor's {sup_label} evaluation for this area demonstrates uniform performance standards across categories."
            ]
            sup_high_msgs = [
                f"Supervisor evaluation rated the predicted category as {sup_label}, significantly exceeding performance in other assessed areas.",
                f"The supervisor's {sup_label} rating for the predicted placement demonstrates exceptional alignment with career expectations.",
                f"Supervisor feedback indicates {sup_label} performance in the predicted area, notably surpassing other evaluated domains.",
                f"The supervisor assigned a {sup_label} rating to the predicted category, highlighting superior competency in this specialization.",
                f"Supervisor assessment shows {sup_label} excellence in the predicted placement, validating the accuracy of career alignment.",
                f"The supervisor's {sup_label} evaluation for this area confirms outstanding performance relative to alternative pathways."
            ]
            sup_low_msgs = [
                f"Supervisor evaluation rated the predicted category as {sup_label}, indicating stronger performance was observed in alternative areas.",
                f"The supervisor's {sup_label} rating for the predicted placement suggests greater alignment with other career pathways.",
                f"Supervisor feedback indicates {sup_label} performance in the predicted area, with enhanced competency demonstrated elsewhere.",
                f"The supervisor assigned a {sup_label} rating to the predicted category, highlighting stronger aptitude in other specializations.",
                f"Supervisor assessment shows {sup_label} competency in the predicted placement, while recognizing superior performance in alternative domains.",
                f"The supervisor's {sup_label} evaluation for this area suggests potential career redirection toward stronger performance areas."
            ]
            
            if abs(sup_corr) < 1e-6:
                correlation_analysis += random.choice(sup_equal_msgs) + ' '
            elif sup_corr > 0:
                correlation_analysis += random.choice(sup_high_msgs) + ' '
            else:
                correlation_analysis += random.choice(sup_low_msgs) + ' '
            # Enhanced self-assessment message variations
            self_equal_msgs = [
                f"Self-evaluation rated the predicted category as {self_label}, demonstrating consistent confidence across all assessed areas.",
                f"The student's {self_label} self-rating for the predicted placement reflects balanced self-awareness of capabilities.",
                f"Self-assessment indicates {self_label} confidence in the predicted area, maintaining consistency with other domain evaluations.",
                f"The student assigned a {self_label} self-rating to the predicted category, showing uniform confidence across specializations.",
                f"Student self-evaluation shows {self_label} perceived competency in the predicted placement, consistent with cross-domain assessment.",
                f"The student's {self_label} self-evaluation for this area demonstrates balanced confidence in diverse professional capabilities."
            ]
            self_high_msgs = [
                f"Self-evaluation rated the predicted category as {self_label}, indicating heightened confidence and affinity for this career path.",
                f"The student's {self_label} self-rating for the predicted placement demonstrates strong personal alignment with career expectations.",
                f"Self-assessment indicates {self_label} confidence in the predicted area, significantly exceeding comfort levels in other domains.",
                f"The student assigned a {self_label} self-rating to the predicted category, highlighting personal recognition of specialized strength.",
                f"Student self-evaluation shows {self_label} perceived excellence in the predicted placement, validating intrinsic career motivation.",
                f"The student's {self_label} self-evaluation for this area confirms strong personal connection and confidence in this career direction."
            ]
            self_low_msgs = [
                f"Self-evaluation rated the predicted category as {self_label}, suggesting greater personal confidence in alternative career pathways.",
                f"The student's {self_label} self-rating for the predicted placement indicates stronger self-perceived alignment elsewhere.",
                f"Self-assessment indicates {self_label} confidence in the predicted area, with enhanced self-assurance demonstrated in other domains.",
                f"The student assigned a {self_label} self-rating to the predicted category, highlighting stronger personal affinity for alternative specializations.",
                f"Student self-evaluation shows {self_label} perceived competency in the predicted placement, while expressing greater confidence in other areas.",
                f"The student's {self_label} self-evaluation for this area suggests personal preference for career paths where self-confidence is higher."
            ]
            
            if abs(self_corr) < 1e-6:
                correlation_analysis += random.choice(self_equal_msgs) + ' '
            elif self_corr > 0:
                correlation_analysis += random.choice(self_high_msgs) + ' '
            else:
                correlation_analysis += random.choice(self_low_msgs) + ' '
            # Enhanced overall correlation analysis
            if sup_corr > 0 and self_corr > 0:
                pos_corr_msgs = [
                    "This comprehensive alignment suggests exceptional predictive accuracy, with both external evaluation and internal confidence validating the career recommendation.",
                    "The convergence of supervisor assessment and self-evaluation in the predicted area demonstrates outstanding career-academic correlation.",
                    "Both objective professional evaluation and subjective personal confidence confirm the precision of the predictive placement model.",
                    "This dual-positive correlation indicates superior career fit, with both workplace performance and personal satisfaction exceeding expectations.",
                    "The synchronized elevation in both supervisor and self-ratings validates the algorithmic precision in career path identification.",
                    "Concurrent excellence in external evaluation and internal confidence suggests optimal career alignment and strong professional development potential."
                ]
                correlation_analysis += random.choice(pos_corr_msgs)
            elif sup_corr < 0 and self_corr < 0:
                neg_corr_msgs = [
                    "This dual-negative correlation suggests the student's optimal career trajectory may align more closely with alternative specialization areas.",
                    "Both professional evaluation and personal assessment indicate stronger alignment with career paths outside the predicted domain.",
                    "The convergent evidence from supervisor and self-evaluation suggests exploring alternative career specializations where performance indicators are more favorable.",
                    "This pattern indicates potential career redirection toward areas where both external assessment and internal confidence demonstrate superior alignment.",
                    "The synchronized lower performance in the predicted area, from both supervisor and self-perspectives, suggests investigating alternative career pathways.",
                    "Concurrent challenges in both professional evaluation and personal confidence indicate the need for career path reconsideration toward stronger performance domains."
                ]
                correlation_analysis += random.choice(neg_corr_msgs)
            else:
                mixed_corr_msgs = [
                    "This divergent correlation pattern suggests nuanced career considerations, where external professional evaluation and internal personal assessment offer different perspectives on optimal placement.",
                    "The mixed correlation indicates complex career dynamics, with supervisor and student perspectives offering complementary insights for comprehensive career planning.",
                    "This varied correlation pattern reflects the multifaceted nature of career fit, suggesting value in considering both external professional feedback and internal personal preferences.",
                    "The contrasting supervisor and self-evaluation trends indicate sophisticated career considerations requiring balanced assessment of both professional competency and personal affinity.",
                    "This differential correlation suggests comprehensive career counseling to reconcile external performance indicators with internal career motivation and confidence.",
                    "The mixed correlation pattern indicates the importance of integrated career planning that considers both professional evaluation outcomes and personal career aspirations."
                ]
                correlation_analysis += random.choice(mixed_corr_msgs)
        else:
            not_enough_corr = [
                "Not enough data in other areas to compare.",
                "There isn't enough information to see how the prediction compares to other areas.",
                "We couldn't check the connection due to missing data in other categories."
            ]
            correlation_analysis += random.choice(not_enough_corr)
    else:
        no_corr_data = [
            "No post-assessment data for the predicted area.",
            "There is no real-world rating for the predicted placement.",
            "We couldn't find post-assessment data for the predicted category."
        ]
        correlation_analysis += random.choice(no_corr_data)

    # --- Conclusion & Recommendation (concise, non-redundant) ---
    conclusion_recommendation = ""
    # Analyze strengths and give a job recommendation (no UI header, frontend will add it)
    import random
    if sup_highest[0] and self_highest[0]:
        if sup_highest[0] == self_highest[0]:
            area = sup_highest[0]
            jobs = job_roles.get(area, [])
            jobs_str = ', '.join(jobs[:3]) if jobs else ''
            agree_templates = [
                f"Unanimous recognition from both supervisor and student identifies <b>{area}</b> as the pinnacle of professional competency. Career opportunities in <b>{jobs_str}</b> represent optimal alignment with demonstrated excellence.",
                f"Convergent assessment from supervisor and student establishes <b>{area}</b> as the primary strength domain. Professional trajectories including <b>{jobs_str}</b> offer exceptional potential for career advancement.",
                f"Collaborative consensus between supervisor and student validates <b>{area}</b> as the cornerstone of professional capability. Strategic career positioning in <b>{jobs_str}</b> would capitalize on this verified expertise.",
                f"Synchronized evaluation from both professional and personal perspectives confirms <b>{area}</b> as the dominant competency area. Career development through <b>{jobs_str}</b> presents ideal growth opportunities.",
                f"Dual-perspective confirmation establishes <b>{area}</b> as the definitive strength specialization. Professional engagement in <b>{jobs_str}</b> would maximize career success potential.",
                f"Unified assessment validates <b>{area}</b> as the exemplary performance domain. Career investment in <b>{jobs_str}</b> represents strategic professional positioning.",
                f"Comprehensive agreement between supervisor and student designates <b>{area}</b> as the superior competency zone. Professional development through <b>{jobs_str}</b> ensures optimal career trajectory.",
                f"Concordant evaluation confirms <b>{area}</b> as the exceptional performance specialization. Career advancement via <b>{jobs_str}</b> leverages proven professional strengths."
            ] if jobs_str else [
                f"Unanimous recognition from both supervisor and student identifies <b>{area}</b> as the pinnacle of professional competency. Career development in any <b>{area}</b> specialization would optimize professional success.",
                f"Convergent assessment establishes <b>{area}</b> as the primary strength domain. Strategic career positioning within <b>{area}</b> offers exceptional advancement potential.",
                f"Collaborative consensus validates <b>{area}</b> as the cornerstone of professional capability. Career focus on <b>{area}</b> specializations would capitalize on demonstrated excellence.",
                f"Synchronized evaluation confirms <b>{area}</b> as the dominant competency area. Professional growth through <b>{area}</b> represents ideal career development.",
                f"Dual-perspective confirmation establishes <b>{area}</b> as the definitive strength specialization. Career engagement in <b>{area}</b> maximizes success potential.",
                f"Unified assessment validates <b>{area}</b> as the exemplary performance domain. Professional investment in <b>{area}</b> ensures strategic career positioning.",
                f"Comprehensive agreement designates <b>{area}</b> as the superior competency zone. Career development in <b>{area}</b> leverages verified professional strengths.",
                f"Concordant evaluation confirms <b>{area}</b> as the exceptional performance specialization. Career advancement through <b>{area}</b> optimizes professional trajectory."
            ]
            summary = random.choice(agree_templates)
            job_marker = f"<!--JOBS:{jobs_str}-->" if jobs_str else ""
            conclusion_recommendation = summary + job_marker
        else:
            area1 = sup_highest[0]
            area2 = self_highest[0]
            jobs1 = job_roles.get(area1, [])
            jobs2 = job_roles.get(area2, [])
            jobs1_str = ', '.join(jobs1[:2]) if jobs1 else ''
            jobs2_str = ', '.join(jobs2[:2]) if jobs2 else ''
            if jobs1_str and jobs2_str:
                diff_templates = [
                    f"Professional assessment identifies <b>{area1}</b> as the primary strength domain, while self-evaluation highlights <b>{area2}</b> competency. This dual-pathway potential suggests exceptional versatility, with career opportunities in both <b>{jobs1_str}</b> and <b>{jobs2_str}</b> offering strategic professional positioning.",
                    f"Supervisor evaluation emphasizes <b>{area1}</b> excellence, complemented by student recognition of <b>{area2}</b> capabilities. This multi-dimensional competency profile supports diverse career exploration through <b>{jobs1_str}</b> or <b>{jobs2_str}</b> specializations.",
                    f"External professional assessment validates <b>{area1}</b> superiority, while internal self-awareness identifies <b>{area2}</b> strength. This comprehensive competency range enables flexible career development via <b>{jobs1_str}</b> or <b>{jobs2_str}</b> pathways.",
                    f"Divergent but complementary perspectives reveal supervisor confidence in <b>{area1}</b> and student affinity for <b>{area2}</b>. This balanced competency distribution creates optimal career flexibility through <b>{jobs1_str}</b> and <b>{jobs2_str}</b> opportunities.",
                    f"Professional evaluation highlights <b>{area1}</b> competency while personal assessment emphasizes <b>{area2}</b> capabilities. This dual-strength profile offers strategic career advantages in both <b>{jobs1_str}</b> and <b>{jobs2_str}</b> domains.",
                    f"Supervisor recognition of <b>{area1}</b> excellence paired with student identification of <b>{area2}</b> strengths creates comprehensive professional versatility. Career development through <b>{jobs1_str}</b> or <b>{jobs2_str}</b> maximizes this multi-domain competency."
                ]
                job_marker = f"<!--JOBS:{jobs1_str},{jobs2_str}-->"
            elif jobs1_str:
                diff_templates = [
                    f"The supervisor sees the student's main strength in <b>{area1}</b>, while the student self-identified <b>{area2}</b>. Jobs like <b>{jobs1_str}</b> would likely be a great fit.",
                    f"Supervisor identified <b>{area1}</b> as the top area, but the student self-identified <b>{area2}</b>. Consider roles such as <b>{jobs1_str}</b> for the student's next steps.",
                    f"There's a difference in perspective: supervisor highlights <b>{area1}</b>, student highlights <b>{area2}</b>. Opportunities in <b>{jobs1_str}</b> could be a natural progression.",
                    f"Supervisor and student see strengths in different areas: <b>{area1}</b> and <b>{area2}</b> respectively. The student may excel in positions like <b>{jobs1_str}</b>."
                ]
                job_marker = f"<!--JOBS:{jobs1_str}-->"
            elif jobs2_str:
                diff_templates = [
                    f"The supervisor sees the student's main strength in <b>{area1}</b>, while the student self-identified <b>{area2}</b>. Jobs like <b>{jobs2_str}</b> would likely be a great fit.",
                    f"Supervisor identified <b>{area1}</b> as the top area, but the student self-identified <b>{area2}</b>. Consider roles such as <b>{jobs2_str}</b> for the student's next steps.",
                    f"There's a difference in perspective: supervisor highlights <b>{area1}</b>, student highlights <b>{area2}</b>. Opportunities in <b>{jobs2_str}</b> could be a natural progression.",
                    f"Supervisor and student see strengths in different areas: <b>{area1}</b> and <b>{area2}</b> respectively. The student may excel in positions like <b>{jobs2_str}</b>."
                ]
                job_marker = f"<!--JOBS:{jobs2_str}-->"
            else:
                diff_templates = [
                    f"The supervisor sees the student's main strength in <b>{area1}</b>, while the student self-identified <b>{area2}</b>. A job in <b>{area1}</b> or <b>{area2}</b> would likely be a great fit.",
                    f"Supervisor identified <b>{area1}</b> as the top area, but the student self-identified <b>{area2}</b>. Opportunities in <b>{area1}</b> or <b>{area2}</b> could be a good match.",
                    f"There's a difference in perspective: supervisor highlights <b>{area1}</b>, student highlights <b>{area2}</b>. The student may thrive in any <b>{area1}</b> or <b>{area2}</b> role.",
                    f"Supervisor and student see strengths in different areas: <b>{area1}</b> and <b>{area2}</b> respectively. A career in <b>{area1}</b> or <b>{area2}</b> is worth considering."
                ]
                job_marker = ""
            summary = random.choice(diff_templates)
            conclusion_recommendation = summary + job_marker
    else:
        no_data_templates = [
            "There isn't enough information from the post-assessment to give a job recommendation based on strengths.",
            "Not enough post-assessment data is available to suggest a job based on strengths.",
            "We couldn't provide a job recommendation due to missing post-assessment information.",
            "Insufficient data from the post-assessment to make a strengths-based job suggestion."
        ]
        conclusion_recommendation = random.choice(no_data_templates)

    # Do not append supervisor comment here; it is shown in a dedicated card on the frontend

    # --- Comparative and Correlation Analysis ---
    # --- Strengths Identified in Post-Assessment ---
    strengths_section = ""
    if sup_highest[0] and self_highest[0]:
        strengths_section += (
            f"<b>Supervisor:</b> {sup_highest[0]} (<b>{likert_label(sup_highest[1][0])}</b>)<br>"
            f"<b>Self:</b> {self_highest[0]} (<b>{likert_label(self_highest[1][1])}</b>)<br>"
        )
        # Add constructive summary
        if sup_highest[0] == self_highest[0]:
            strengths_section += f"<br>This student demonstrated strong performance in <b>{sup_highest[0]}</b>, as recognized by both the supervisor and self-assessment."
        else:
            strengths_section += f"<br>The supervisor identified <b>{sup_highest[0]}</b> as the student's greatest strength, while the student self-identified <b>{self_highest[0]}</b>. This highlights versatility and capability in multiple areas."
    else:
        strengths_section = "Insufficient post-assessment data to determine strengths."
    comparative_analysis = ""
    correlation_analysis = ""
    # 1. Comparative: Does the predicted placement match the highest post-assessment rating?
    # Find the category with the highest supervisor and self post-assessment average
    post_categories = {
        'Systems Development': (data.get('post_systems_development_avg'), data.get('self_systems_development_avg')),
        'Research': (data.get('post_research_avg'), data.get('self_research_avg')),
        'Business Operations': (data.get('post_business_operations_avg'), data.get('self_business_operations_avg')),
        'Technical Support': (data.get('post_technical_support_avg'), data.get('self_technical_support_avg'))
    }
    # Supervisor highest
    sup_highest = max(post_categories.items(), key=lambda x: (x[1][0] if x[1][0] is not None else -1))
    # Self highest
    self_highest = max(post_categories.items(), key=lambda x: (x[1][1] if x[1][1] is not None else -1))

    # Predicted placement (move up so it's available for all logic)
    predicted = data.get('ojt_placement')

    # Build comparative analysis string
    sup_pred = post_categories.get(predicted, (None, None))[0]
    self_pred = post_categories.get(predicted, (None, None))[1]
    sup_pred_label = likert_label(sup_pred)
    self_pred_label = likert_label(self_pred)
    comp_intro_templates = [
        f"Algorithmic analysis of academic performance data identified <b>{predicted}</b> as the optimal career trajectory for this student profile.",
        f"Predictive modeling based on comprehensive grade evaluation recommended <b>{predicted}</b> as the primary placement specialization.",
        f"Statistical assessment of academic achievements indicated <b>{predicted}</b> as the most suitable professional development pathway.",
        f"Machine learning analysis of educational performance data determined <b>{predicted}</b> as the ideal career alignment opportunity.",
        f"Computational evaluation of academic competencies suggested <b>{predicted}</b> as the premier placement recommendation.",
        f"Data-driven analysis of scholastic performance identified <b>{predicted}</b> as the strategic career positioning for optimal success.",
        f"Algorithmic assessment of educational achievements recommended <b>{predicted}</b> as the primary professional specialization pathway.",
        f"Predictive analysis of academic performance indicators determined <b>{predicted}</b> as the most advantageous career direction."
    ]
    comp_rating_templates = [
        f"Post-placement professional evaluation yielded supervisor assessment of <b>{sup_pred_label}</b> performance, with corresponding student self-evaluation rated as <b>{self_pred_label}</b> competency.",
        f"Workplace assessment resulted in supervisor rating of <b>{sup_pred_label}</b> for this specialization, while student self-assessment indicated <b>{self_pred_label}</b> confidence level.",
        f"Professional evaluation outcomes show supervisor assessment of <b>{sup_pred_label}</b> performance coupled with student self-rating of <b>{self_pred_label}</b> capability.",
        f"Post-placement analysis reveals supervisor evaluation of <b>{sup_pred_label}</b> competency and student self-assessment of <b>{self_pred_label}</b> proficiency.",
        f"Workplace performance assessment indicates supervisor rating of <b>{sup_pred_label}</b> achievement with student self-evaluation of <b>{self_pred_label}</b> satisfaction.",
        f"Professional assessment outcomes demonstrate supervisor evaluation of <b>{sup_pred_label}</b> capability and student self-rating of <b>{self_pred_label}</b> performance."
    ]
    comparative_analysis += random.choice(comp_intro_templates) + ' ' + random.choice(comp_rating_templates) + ' '
    if sup_pred is not None and self_pred is not None:
        if sup_pred >= 3.75 and self_pred >= 3.75:
            match_templates = [
                "Exceptional correlation validates the predictive accuracy, with both external professional evaluation and internal student assessment confirming outstanding alignment between academic forecasting and workplace reality.",
                "Outstanding predictive precision demonstrated through convergent high-performance ratings from both supervisor and student, establishing exceptional model reliability for career trajectory prediction.",
                "Superior algorithmic accuracy validated by dual-perspective excellence ratings, confirming the robust correlation between academic performance indicators and professional workplace success.",
                "Excellent predictive model validation evidenced through synchronized high-performance evaluations from both external professional assessment and internal student confidence measurement.",
                "Remarkable forecasting accuracy demonstrated via concordant superior ratings from supervisor and student perspectives, validating the precision of academic-to-career transition predictions.",
                "Outstanding model reliability confirmed through dual high-performance validation, establishing strong correlation between pre-placement academic analysis and post-placement professional success."
            ]
            comparative_analysis += random.choice(match_templates)
        elif sup_pred >= 3.75 or self_pred >= 3.75:
            partial_templates = [
                "Either the supervisor or the student gave a high mark for this placement, so the prediction is at least partly supported by the real work experience.",
                "There’s some agreement between the academic prediction and the work experience, but not a perfect match.",
                "The prediction was somewhat reflected in the actual placement, but not fully."
            ]
            comparative_analysis += random.choice(partial_templates)
        else:
            low_templates = [
                "The marks for this placement are average or low, so the prediction wasn’t clearly seen during the actual work experience.",
                "It seems the prediction didn’t fully match the real-world experience this time.",
                "The academic prediction and the actual placement were different, which is perfectly normal."
            ]
            comparative_analysis += random.choice(low_templates)
    else:
        no_data_templates = [
            "Comprehensive correlation analysis requires complete post-assessment data from both supervisor and student perspectives to validate predictive model accuracy.",
            "Insufficient post-placement evaluation data limits the ability to perform thorough correlation analysis between academic predictions and workplace performance outcomes.",
            "Complete predictive model validation necessitates comprehensive post-assessment data collection from both external professional evaluation and internal student assessment.",
            "Thorough correlation analysis between academic forecasting and workplace reality requires complete post-placement performance data from multiple evaluation perspectives.",
            "Comprehensive model accuracy assessment depends on complete post-assessment data collection to enable meaningful correlation analysis between predictions and outcomes.",
            "Full predictive model validation requires comprehensive post-placement evaluation data to establish meaningful correlation between academic indicators and professional performance."
        ]
        comparative_analysis += random.choice(no_data_templates)

    # 2. Correlation: Is there a relationship between predicted placement and post-assessment ratings?
    # For simplicity, compare predicted category's supervisor/self avg to the other categories
    pred_sup = post_categories.get(predicted, (None, None))[0]
    pred_self = post_categories.get(predicted, (None, None))[1]
    if pred_sup is not None and pred_self is not None:
        avg_other_sup = [v[0] for k, v in post_categories.items() if k != predicted and v[0] is not None]
        avg_other_self = [v[1] for k, v in post_categories.items() if k != predicted and v[1] is not None]
        if avg_other_sup and avg_other_self:
            sup_corr = pred_sup - (sum(avg_other_sup) / len(avg_other_sup))
            self_corr = pred_self - (sum(avg_other_self) / len(avg_other_self))
            sup_label = likert_label(pred_sup)
            self_label = likert_label(pred_self)
            # Supervisor message
            if abs(sup_corr) < 1e-6:
                correlation_analysis += f"Supervisor rating in predicted category is {sup_label}, equal to the average of other categories. "
            elif sup_corr > 0:
                correlation_analysis += f"Supervisor rating in predicted category is {sup_label}, higher than average of other categories. "
            else:
                correlation_analysis += f"Supervisor rating in predicted category is {sup_label}, lower than average of other categories. "
            # Self message
            if abs(self_corr) < 1e-6:
                correlation_analysis += f"Self rating in predicted category is {self_label}, equal to the average of other categories. "
            elif self_corr > 0:
                correlation_analysis += f"Self rating in predicted category is {self_label}, higher than average of other categories. "
            else:
                correlation_analysis += f"Self rating in predicted category is {self_label}, lower than average of other categories. "
            if sup_corr > 0 and self_corr > 0:
                correlation_analysis += "\nThis suggests a positive correlation between the model's prediction and real-world performance."
            elif sup_corr < 0 and self_corr < 0:
                correlation_analysis += "\nThis suggests a negative correlation: the student performed better in other categories than the predicted one."
            else:
                correlation_analysis += "\nThe correlation is mixed: supervisor and self ratings differ in relation to the prediction."
        else:
            correlation_analysis += "Not enough data in other categories to compute correlation."
    else:
        correlation_analysis += "No post-assessment data for the predicted category."

    return jsonify({
        'placement': placement,
        'reasoning': reasoning,
        'probabilities': probabilities,
        'prob_explanation': prob_explanation,
        'post_assessment_averages': post_assessment_averages,
        'conclusion_recommendation': conclusion_recommendation,
        'comparative_analysis': comparative_analysis,
        'correlation_analysis': correlation_analysis,
        'supervisor_comment': supervisor_comment
        , 'strengths_post_assessment': strengths_section
    })

@app.route('/post_analysis_demo', methods=['GET'])
def post_analysis_demo():
    """Demo endpoint that generates sample post-analysis data for testing"""
    import random
    
    # Generate realistic demo data
    placements = ['Systems Development', 'Research', 'Business Operations', 'Technical Support']
    selected_placement = random.choice(placements)
    
    # Generate realistic grades (75-95)
    grades = {col: random.randint(75, 95) for col in feature_cols if col not in ['soft_skill', 'communication_skill', 'technical_skill']}
    
    # Generate realistic skills (3.0-5.0)
    skills = {
        'soft_skill': round(random.uniform(3.0, 5.0), 2),
        'communication_skill': round(random.uniform(3.0, 5.0), 2),
        'technical_skill': round(random.uniform(3.0, 5.0), 2)
    }
    
    # Generate realistic post-assessment data
    post_data = {}
    for placement in placements:
        supervisor_avg = round(random.uniform(2.5, 4.8), 2)
        self_avg = round(random.uniform(2.5, 4.8), 2)
        post_data[f'post_{placement.lower().replace(" ", "_")}_avg'] = supervisor_avg
        post_data[f'self_{placement.lower().replace(" ", "_")}_avg'] = self_avg
    
    # Generate probabilities
    probabilities = {}
    remaining_prob = 100
    for i, placement in enumerate(placements[:-1]):
        if i == 0:  # First placement gets higher probability
            prob = round(random.uniform(35, 65), 1)
        else:
            prob = round(random.uniform(10, remaining_prob * 0.6), 1)
        probabilities[placement] = prob
        remaining_prob -= prob
    probabilities[placements[-1]] = max(5.0, remaining_prob)
    
    # Generate reasoning using the same logic as predict endpoint
    reasoning_templates = [
        f"Demo analysis for {selected_placement}. Student demonstrates strong academic performance with balanced skill development across technical and interpersonal domains.",
        f"Comprehensive evaluation indicates {selected_placement} as optimal placement. Academic excellence combined with demonstrated competencies support this career trajectory.",
        f"Assessment results favor {selected_placement} based on integrated analysis of academic performance and professional skill indicators."
    ]
    
    demo_reasoning = random.choice(reasoning_templates)
    
    # Simulate supervisor comment
    supervisor_comments = [
        "Student showed excellent progress during the placement period. Strong work ethic and good learning capacity.",
        "Demonstrated solid understanding of tasks and showed initiative in problem-solving situations.",
        "Good communication skills and ability to work well within team structures. Reliable performance overall.",
        "Strong technical aptitude with room for continued growth. Positive attitude towards learning new concepts.",
        "Effective collaboration and professional demeanor. Adapts well to workplace expectations and standards."
    ]
    
    return jsonify({
        'placement': selected_placement,
        'reasoning': demo_reasoning,
        'probabilities': probabilities,
        'prob_explanation': f"Demo analysis shows {selected_placement} leading with {probabilities[selected_placement]}% probability, indicating strong alignment with student capabilities and career objectives.",
        'post_assessment_averages': [
            {'category': placement, 
             'supervisor_avg': post_data.get(f'post_{placement.lower().replace(" ", "_")}_avg'),
             'self_avg': post_data.get(f'self_{placement.lower().replace(" ", "_")}_avg')}
            for placement in placements
        ],
        'conclusion_recommendation': f"Demo recommendation: Both supervisor and student assessments indicate strong performance in {selected_placement}. Career opportunities in this field align well with demonstrated competencies.",
        'comparative_analysis': f"Demo comparative analysis: Predicted placement of {selected_placement} correlates well with post-assessment performance indicators, suggesting effective career guidance accuracy.",
        'correlation_analysis': f"Demo correlation analysis: Performance metrics in {selected_placement} demonstrate positive alignment between academic predictions and workplace performance outcomes.",
        'supervisor_comment': random.choice(supervisor_comments),
        'strengths_post_assessment': f"<b>Supervisor:</b> {selected_placement} (<b>Very Good</b>)<br><b>Self:</b> {selected_placement} (<b>Good</b>)<br><br>Demo analysis shows consistent performance recognition from both perspectives.",
        'demo_mode': True
    })

if __name__ == "__main__":
    # Force Flask to use port 5000 internally (ignore Render's PORT environment variable)
    port = 5000
    print(f"\n🚀 Starting Flask API on port {port}")
    print(f"📊 Model loaded: {'✅' if model_loaded else '❌'}")
    print(f"💾 Database connected: {'✅' if database_connected else '❌'}")
    print(f"🔧 Features available: {len(feature_cols) if feature_cols else 0}")
    print(f"📡 Health endpoint: http://localhost:{port}/health")
    print("🎯 Flask API starting...\n")
    
    try:
        app.run(host='0.0.0.0', port=port, debug=False)
    except Exception as e:
        print(f"❌ Flask failed to start: {str(e)}")
        raise
