from flask import Flask, request, jsonify
from flask_cors import CORS
import joblib
import pandas as pd
import random
from sklearn.preprocessing import LabelEncoder
import os
from dotenv import load_dotenv

# Load environment variables from .env file
env_path = os.path.join(os.path.dirname(__file__), '..', '..', '.env')
load_dotenv(env_path)

app = Flask(__name__)
CORS(app, resources={r"/*": {"origins": "*"}})

# Load model and label encoder
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
model_path = os.path.join(BASE_DIR, '..', 'model', 'pre-assessment.joblib')
clf = joblib.load(model_path)

# Use MySQL database table instead of CSV
from sqlalchemy import create_engine

# Get database credentials from environment variables
user = os.getenv('DB_USERNAME', 'root')
password = os.getenv('DB_PASSWORD', '')
host = os.getenv('DB_HOST', 'localhost:3306').split(':')[0]  # Remove port from host
port = int(os.getenv('DB_HOST', 'localhost:3306').split(':')[1]) if ':' in os.getenv('DB_HOST', 'localhost:3306') else 3306
database = os.getenv('DB_NAME', 'attendancetrackernp')

print(f"Connecting to database: {user}@{host}:{port}/{database}")

engine = create_engine(f"mysql+pymysql://{user}:{password}@{host}:{port}/{database}")
df_ref = pd.read_sql('SELECT * FROM past_data', con=engine)
print('Loaded data from database:')
print(df_ref.head())
le = LabelEncoder()
le.fit(df_ref['OJT Placement'])

# Get feature columns
feature_cols = [col for col in df_ref.columns if col not in ['id_number','student_name','year_graduated','OJT Placement']]

@app.route('/predict', methods=['POST'])
def predict():
    data = request.json
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

    # Focused reasoning: only top 3 grades from assigned category, show average
    # Category mapping (should match your backend logic)
    categories = {
        'Systems Development': ['CC 102', 'CC 103', 'PF 101', 'CC 104', 'CC 106', 'CC 105', 'WS 101', 'CAP 101', 'CAP 102', 'IPT 101', 'IPT 102'],
        'Research': ['CAP 101', 'CAP 102', 'IM 101', 'IM 102', 'HCI 101', 'HCI 102'],
        'Business Operations': ['IM 101', 'IM 102', 'SP 101'],
        'Technical Support': ['NET 101', 'NET 102', 'IAS 101', 'IAS 102']
    }
    cat_subjects = categories.get(pred_label, [])
    # Get subject grades for assigned category
    subject_grades = [(subj, float(request.json.get(subj, 0))) for subj in cat_subjects if subj in feature_cols]
    # Sort by grade descending
    subject_grades_sorted = sorted(subject_grades, key=lambda x: x[1], reverse=True)
    top_subjects = subject_grades_sorted[:3]
    if top_subjects:
        feature_analysis = ', '.join([f"{name}: {round(value)}" for name, value in top_subjects])
        reasoning = f"Recommended for {pred_label} due to strong performance in: {feature_analysis}."
    else:
        reasoning = f"Recommended for {pred_label}."

    # Dynamic supporting message based on Soft Skill and Communication Skill
    soft_skill = float(request.json.get('soft_skill', 0))
    comm_skill = float(request.json.get('communication_skill', 0))
    support_msg = ""
    if soft_skill >= 4.5 and comm_skill >= 4.5:
        support_msg = "Both soft skill and communication skill ratings reinforce the suitability of this placement."
    elif soft_skill >= 4.5:
        support_msg = "Additionally, the student's high soft skill rating further strengthens this recommendation."
    elif comm_skill >= 4.5:
        support_msg = "The student's strong communication skills further validate this OJT assignment."
    elif soft_skill < 4.0 and comm_skill < 4.0:
        support_msg = "Despite lower soft/communication skill ratings, the technical grades indicate this is the best placement."
    elif soft_skill > comm_skill:
        support_msg = "The student's outstanding soft skills provide additional support for this assignment, even if communication skills are moderate."
    elif comm_skill > soft_skill:
        support_msg = "Strong communication skills play a key role in backing up this placement recommendation."
    else:
        support_msg = "While technical grades drive this decision, the student's average soft and communication skills are also considered."

    full_reasoning = reasoning
    if support_msg:
        full_reasoning += "\n\n" + support_msg

    # Add probability explanation
    sorted_probs = sorted(proba_dict.items(), key=lambda x: x[1], reverse=True)
    top_label, top_prob = sorted_probs[0]
    second_label, second_prob = sorted_probs[1]
    third_label, third_prob = sorted_probs[2]
    lowest_label, lowest_prob = sorted_probs[3]

    # Scenario-based probability explanations
    prob_explanation = ""
    if top_prob >= 50:
        prob_explanation = (
            f"The model is highly confident ({top_prob}%) that {top_label} is the best placement for this student. "
            f"Other categories have much lower probabilities, indicating a clear fit."
        )
    elif top_prob - second_prob < 10:
        prob_explanation = (
            f"The model finds both {top_label} ({top_prob}%) and {second_label} ({second_prob}%) to be strong options, "
            f"suggesting the student is well-suited for either placement. The final decision may benefit from additional context."
        )
    elif top_prob >= 35 and second_prob >= 25:
        prob_explanation = (
            f"{top_label} is recommended (confidence: {top_prob}%), but {second_label} ({second_prob}%) and {third_label} ({third_prob}%) are also notable, "
            f"showing the student has strengths in several areas."
        )
    elif lowest_prob > 15:
        prob_explanation = (
            f"All categories have moderate probabilities, with {top_label} ({top_prob}%) leading. This suggests the student is versatile and could succeed in multiple placements."
        )
    else:
        prob_explanation = (
            f"The model predicts {top_label} as the most suitable placement, with {top_prob}% confidence. "
            f"There is also notable probability for {second_label} ({second_prob}%) and {third_label} ({third_prob}%), "
            f"indicating the student has strengths in multiple areas. {lowest_label} is less likely ({lowest_prob}%), "
            f"suggesting fewer relevant grades or skills for that category."
        )


    return jsonify({'placement': pred_label, 'reasoning': full_reasoning, 'probabilities': proba_dict, 'prob_explanation': prob_explanation})


# --- Post-Analysis Sample Output Endpoint ---
@app.route('/post_analysis', methods=['GET'])
def post_analysis():
    import random
    # Example job roles for each area
    job_roles = {
        'Systems Development': ["Software Developer", "Web Developer", "Application Programmer", "Systems Analyst"],
        'Research': ["Research Assistant", "Data Analyst", "Academic Researcher", "Market Researcher"],
        'Business Operations': ["Business Analyst", "Operations Coordinator", "Administrative Assistant", "Project Coordinator"],
        'Technical Support': ["IT Support Specialist", "Help Desk Technician", "Network Support", "Technical Support Representative"]
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
    student_id = request.args.get('student_id')
    if not student_id:
        return jsonify({'error': 'student_id is required'}), 400
    try:
        student_id_int = int(student_id)
    except Exception as e:
        print('Invalid student_id:', student_id, e)
        return jsonify({'error': 'student_id must be an integer'}), 400

    debug_info = {
        'student_id': student_id_int,
        'student_id_type': str(type(student_id_int)),
        'query': 'SELECT * FROM pre_assessment WHERE STUDENT_ID = :student_id',
        'table': 'pre_assessment'
    }
    print('Looking up STUDENT_ID:', student_id_int, type(student_id_int))
    print('Querying table: pre_assessment with SQL: SELECT * FROM pre_assessment WHERE STUDENT_ID = :student_id')

    # Query pre_assessment table for the student
    from sqlalchemy import text
    query = "SELECT * FROM pre_assessment WHERE STUDENT_ID = :student_id"
    with engine.connect() as conn:
        print('Looking up STUDENT_ID:', student_id_int)
        all_rows = conn.execute(text("SELECT STUDENT_ID FROM pre_assessment")).fetchall()
        print('All STUDENT_IDs in pre_assessment as seen by Flask:', [r[0] for r in all_rows])
        result = conn.execute(text(query), {'student_id': student_id_int})
        row = result.fetchone()

    if not row:
        return jsonify({'error': 'Student not found', 'debug': debug_info}), 404

    # Convert row to dict (SQLAlchemy RowProxy to dict)
    data = dict(row._mapping)

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

    # Check if all key columns are empty/null
    key_fields = [
        placement,
        reasoning,
        data.get('prediction_probabilities'),
        data.get('post_systems_development_avg'),
        data.get('post_research_avg'),
        data.get('post_business_operations_avg'),
        data.get('post_technical_support_avg'),
        data.get('self_systems_development_avg'),
        data.get('self_research_avg'),
        data.get('self_business_operations_avg'),
        data.get('self_technical_support_avg'),
        data.get('supervisor_comment')
    ]
    if all(f is None or f == '' for f in key_fields):
        return jsonify({'error': 'No post-analysis data found for this student.'}), 404

    # Probability explanation (simple logic based on values)
    prob_explanation = ''
    if probabilities:
        sorted_probs = sorted(probabilities.items(), key=lambda x: x[1], reverse=True)
        if len(sorted_probs) >= 2:
            top_label, top_prob = sorted_probs[0]
            second_label, second_prob = sorted_probs[1]
            if float(top_prob) >= 50:
                prob_explanation = f"The model is highly confident ({top_prob}%) that {top_label} is the best placement for this student. Other categories have much lower probabilities, indicating a clear fit."
            elif float(top_prob) - float(second_prob) < 10:
                prob_explanation = f"The model finds both {top_label} ({top_prob}%) and {second_label} ({second_prob}%) to be strong options, suggesting the student is well-suited for either placement. The final decision may benefit from additional context."
            else:
                prob_explanation = f"The model predicts {top_label} as the most suitable placement, with {top_prob}% confidence."

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
            # Supervisor message
            sup_msgs = [
                f"Supervisor rating in predicted category is {sup_label}, equal to the average of other categories.",
                f"Supervisor gave a {sup_label} for the predicted area, which is about the same as other areas.",
                f"Supervisor's score for this area is {sup_label}, similar to their ratings elsewhere."
            ]
            if abs(sup_corr) < 1e-6:
                correlation_analysis += random.choice(sup_msgs) + ' '
            elif sup_corr > 0:
                sup_high_msgs = [
                    f"Supervisor rating in predicted category is {sup_label}, higher than average of other categories.",
                    f"Supervisor gave a higher score in the predicted area than in others.",
                    f"Supervisor's rating for this area stands out above the rest."
                ]
                correlation_analysis += random.choice(sup_high_msgs) + ' '
            else:
                sup_low_msgs = [
                    f"Supervisor rating in predicted category is {sup_label}, lower than average of other categories.",
                    f"Supervisor gave a lower score in the predicted area than in others.",
                    f"Supervisor's rating for this area is below their other ratings."
                ]
                correlation_analysis += random.choice(sup_low_msgs) + ' '
            # Self message
            self_msgs = [
                f"Self rating in predicted category is {self_label}, equal to the average of other categories.",
                f"Student gave themselves a {self_label} here, about the same as in other areas.",
                f"Self-assessment for this area is {self_label}, similar to other categories."
            ]
            if abs(self_corr) < 1e-6:
                correlation_analysis += random.choice(self_msgs) + ' '
            elif self_corr > 0:
                self_high_msgs = [
                    f"Self rating in predicted category is {self_label}, higher than average of other categories.",
                    f"Student gave themselves a higher score in the predicted area than in others.",
                    f"Self-assessment for this area stands out above the rest."
                ]
                correlation_analysis += random.choice(self_high_msgs) + ' '
            else:
                self_low_msgs = [
                    f"Self rating in predicted category is {self_label}, lower than average of other categories.",
                    f"Student gave themselves a lower score in the predicted area than in others.",
                    f"Self-assessment for this area is below their other ratings."
                ]
                correlation_analysis += random.choice(self_low_msgs) + ' '
            # Overall correlation
            if sup_corr > 0 and self_corr > 0:
                pos_corr_msgs = [
                    "This suggests a positive connection between the prediction and the real-world performance.",
                    "Both supervisor and student did better in the predicted area, showing a good match.",
                    "The prediction and actual performance go hand in hand here."
                ]
                correlation_analysis += random.choice(pos_corr_msgs)
            elif sup_corr < 0 and self_corr < 0:
                neg_corr_msgs = [
                    "This suggests a negative connection: the student performed better in other areas than the predicted one.",
                    "Both supervisor and student did better in other areas, so the prediction didn't match as well.",
                    "The prediction and actual performance were not closely linked in this case."
                ]
                correlation_analysis += random.choice(neg_corr_msgs)
            else:
                mixed_corr_msgs = [
                    "The connection is mixed: supervisor and student ratings differ in relation to the prediction.",
                    "Supervisor and student had different experiences in the predicted area.",
                    "There's a mix of results between the prediction and the real-world ratings."
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
                f"Both the supervisor and the student agree that the student's greatest strength is in <b>{area}</b>. Jobs like <b>{jobs_str}</b> would likely be a great fit.",
                f"Supervisor and student both recognized <b>{area}</b> as the student's standout area. Consider roles such as <b>{jobs_str}</b> for the student's next steps.",
                f"There is a shared view that <b>{area}</b> is where the student shines most. Opportunities in <b>{jobs_str}</b> could be a natural progression.",
                f"Consensus from both supervisor and student points to <b>{area}</b> as the top strength. The student may excel in positions like <b>{jobs_str}</b>."
            ] if jobs_str else [
                f"Both the supervisor and the student agree that the student's greatest strength is in <b>{area}</b>. A job in <b>{area}</b> would likely be a great fit.",
                f"Supervisor and student both recognized <b>{area}</b> as the student's standout area. Opportunities in <b>{area}</b> could be a good match.",
                f"There is a shared view that <b>{area}</b> is where the student shines most. The student may thrive in any <b>{area}</b> role.",
                f"Consensus from both supervisor and student points to <b>{area}</b> as the top strength. A career in <b>{area}</b> is worth considering."
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
                    f"The supervisor sees the student's main strength in <b>{area1}</b>, while the student self-identified <b>{area2}</b>. Both <b>{jobs1_str}</b> and <b>{jobs2_str}</b> roles are worth exploring.",
                    f"Supervisor identified <b>{area1}</b> as the top area, but the student self-identified <b>{area2}</b>. The student could do well in either area, so jobs like <b>{jobs1_str}</b> or <b>{jobs2_str}</b> are both good options.",
                    f"There's a difference in perspective: supervisor highlights <b>{area1}</b>, student highlights <b>{area2}</b>. Opportunities in <b>{jobs1_str}</b> or <b>{jobs2_str}</b> could suit the student's strengths.",
                    f"Supervisor and student see strengths in different areas: <b>{area1}</b> and <b>{area2}</b> respectively. A career in either <b>{area1}</b> or <b>{area2}</b> is a strong possibility."
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
        f"The predicted placement based on academic results is <b>{predicted}</b>.",
        f"Based on the grades, the suggested placement is <b>{predicted}</b>.",
        f"According to academic results, <b>{predicted}</b> was the recommended placement.",
        f"The model suggested <b>{predicted}</b> as the best fit based on grades."
    ]
    comp_rating_templates = [
        f"In the post-assessment, the supervisor rated the student in this placement as <b>{sup_pred_label}</b> and the student self-rated as <b>{self_pred_label}</b>.",
        f"For this placement, the supervisor gave a rating of <b>{sup_pred_label}</b> and the student gave <b>{self_pred_label}</b>.",
        f"Supervisor's rating for this area: <b>{sup_pred_label}</b>; student's own rating: <b>{self_pred_label}</b>.",
        f"Supervisor: <b>{sup_pred_label}</b>, Self: <b>{self_pred_label}</b> for this placement."
    ]
    comparative_analysis += random.choice(comp_intro_templates) + ' ' + random.choice(comp_rating_templates) + ' '
    if sup_pred is not None and self_pred is not None:
        if sup_pred >= 3.75 and self_pred >= 3.75:
            match_templates = [
                "Both the supervisor and the student gave high marks for this placement, showing a strong match between the prediction and the real work experience.",
                "Great job! The prediction and the actual work experience lined up well, according to both the supervisor and the student.",
                "There is a clear agreement between the prediction and what happened during the placement."
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
            "There isn't enough information from the post-assessment to see if the prediction matches the real work experience.",
            "Not enough post-assessment data is available to compare with the prediction.",
            "We couldn't compare the prediction to the real work experience due to missing data."
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

if __name__ == "__main__":
    port = int(os.environ.get('PORT', 5000))
    app.run(host='0.0.0.0', port=port, debug=False)
