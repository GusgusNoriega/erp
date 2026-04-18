ALTER TABLE employee_day_schedules
    ADD UNIQUE KEY employee_day_schedules_employee_date_unique (employee_id, work_date);

ALTER TABLE attendance_punches
    ADD UNIQUE KEY attendance_punches_employee_date_time_unique (employee_id, work_date, punch_time);

ALTER TABLE attendance_daily_exceptions
    ADD UNIQUE KEY attendance_daily_exceptions_employee_date_unique (employee_id, work_date);

ALTER TABLE attendance_period_summaries
    ADD UNIQUE KEY attendance_period_summaries_employee_period_unique (employee_id, period_start, period_end);

