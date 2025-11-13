
# TODO: Prediction Tab – Student OJT Placement Prediction Workflow

## Objective
Implement a Prediction tab in the dashboard to display all students under the logged-in coordinator, showing:
- STUDENT NAME (SURNAME, NAME)
- HTE ASSIGNED
- STATUS (Rated/Not Rated)
- PREDICTED PLACEMENT (from ML model)


## Workflow & Data Mapping
1. Display student list in a table with the above columns.
	- STUDENT NAME: Combine SURNAME and NAME from `interns_details`.
	- HTE ASSIGNED: Join `intern_details` (HTE_ID) to `host_training_establishment` (NAME).
	- STATUS: Check `pre_assessment.soft_skill` and `pre_assessment.communication_skill` for each STUDENT_ID. If both are not null, mark as Rated; else Not Rated.
	- PREDICTED PLACEMENT: Use ML model with data from `pre_assessment` for each student.
2. Add a "Run Prediction" button to trigger the workflow:
	- For each student, verify all required columns in `pre_assessment` (subject grades, soft_skill, communication_skill) are present and valid.
	- Show spinner/loading during validation.
	- Indicate status: ✔️ for complete, ❌ for missing data (with tooltip explanation).
	- For ✔️ students, call ML model (Flask `/predict` endpoint) and display predicted placement.
	- Allow re-running predictions after data updates.
	- Optionally show "Last Predicted" timestamp.


## Backend
- Create PHP endpoint (e.g., `ajaxhandler/predictionAjax.php`) to:
	- Fetch students under coordinator using joins:
		- `coordinator` → `internship_needs` (COORDINATOR_ID) → `intern_details` (HTE_ID) → `interns_details` (INTERNS_ID)
	- For each student, join to `pre_assessment` for grades and ratings.
	- Check completeness of required columns in `pre_assessment`.
	- Call ML model for predictions.
	- Return results/status/errors/tooltips.

## Frontend
- Update Prediction tab in `mainDashboard.php`:
	- Table display
	- Button and spinner
	- AJAX calls to backend
	- Status indicators and tooltips

## ML Model
- Flask app (`ML/sample_frontend/app.py`) exposes `/predict` endpoint
- Accepts student features, returns placement, reasoning, probabilities

## Progress
- [ ] Backend: student fetch, verification, ML call
- [ ] Frontend: table, button, AJAX, UI updates
- [ ] ML: confirm endpoint and feature mapping
- [ ] Testing and UI polish
- [ ]

