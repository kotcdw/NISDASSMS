<?php
/** @var Router $router */

// Public
$router->get('', 'DashboardController@home', null);
$router->get('login', 'AuthController@showLogin', null);
$router->post('login', 'AuthController@login', null);
$router->post('logout', 'AuthController@logout');
$router->get('apply', 'ApplyController@form', null);
$router->post('apply', 'ApplyController@submit', null);
$router->get('apply/done', 'ApplyController@done', null);
$router->get('apply/status', 'ApplyController@status', null);
$router->post('apply/status', 'ApplyController@checkStatus', null);

// Account
$router->get('dashboard', 'DashboardController@index', 'staff');
$router->get('dashboard/widget/{slug}', 'DashboardController@widget', 'staff');
$router->get('dashboard/live', 'DashboardController@live', 'staff');
$router->get('login/verify', 'AuthController@showVerify', null);
$router->post('login/verify', 'AuthController@verify', null);
$router->get('account/security', 'AccountController@security');
$router->get('account/2fa', 'AccountController@setup2fa');
$router->post('account/2fa', 'AccountController@enable2fa');
$router->post('account/2fa/disable', 'AccountController@disable2fa');
$router->post('account/2fa/codes', 'AccountController@newCodes');
$router->post('account/sessions/{id}/revoke', 'AccountController@revokeSession');
$router->post('account/sessions/revoke-others', 'AccountController@revokeOthers');
$router->get('account/confirm', 'AccountController@showConfirm');
$router->post('account/confirm', 'AccountController@confirm');
$router->get('account/password', 'AuthController@showPassword');
$router->post('account/password', 'AuthController@changePassword');

// Setup
$router->get('setup', 'SetupController@index', 'app.configure');
$router->post('setup/school', 'SetupController@saveSchool', 'app.configure');
$router->post('setup/migrate', 'SetupController@migrate', 'users.manage');
$router->get('setup/years', 'SetupController@years', 'app.configure');
$router->post('setup/years', 'SetupController@saveYear', 'app.configure');
$router->post('setup/years/{id}/current', 'SetupController@setCurrentYear', 'app.configure');
$router->post('setup/terms/{id}', 'SetupController@saveTerm', 'app.configure');
$router->post('setup/terms/{id}/current', 'SetupController@setCurrentTerm', 'app.configure');
$router->get('setup/classes', 'SetupController@classes', 'app.configure');
$router->post('setup/classes', 'SetupController@saveClass', 'app.configure');
$router->post('setup/classes/{id}', 'SetupController@saveClass', 'app.configure');
$router->get('setup/subjects', 'SetupController@subjects', 'app.configure');
$router->post('setup/subjects', 'SetupController@saveSubject', 'app.configure');
$router->post('setup/subjects/{id}', 'SetupController@saveSubject', 'app.configure');
$router->get('setup/columns', 'ListColumnsController@index', 'app.configure');
$router->post('setup/columns', 'ListColumnsController@save', 'app.configure');
$router->get('setup/modules', 'ModuleSwitchController@index', 'modules.switch');
$router->post('setup/modules', 'ModuleSwitchController@save', 'modules.switch');
$router->get('setup/privacy', 'PrivacyController@settings', 'app.configure');
$router->post('setup/privacy', 'PrivacyController@save', 'app.configure');
$router->post('setup/privacy/anonymise', 'PrivacyController@anonymise', 'users.manage');
$router->get('privacy', 'PrivacyController@notice', null);

// Users
$router->get('users', 'UserController@index', 'users.manage');
$router->get('users/create', 'UserController@create', 'users.manage');
$router->post('users', 'UserController@store', 'users.manage');
$router->get('users/{id}/edit', 'UserController@edit', 'users.manage');
$router->post('users/{id}', 'UserController@update', 'users.manage');
$router->post('users/{id}/reset', 'UserController@resetPassword', 'users.manage');
$router->post('users/{id}/signout', 'UserController@signOutEverywhere', 'users.manage');
$router->post('users/{id}/reset-2fa', 'UserController@reset2fa', 'users.manage');

// Roles & security (Super Admin)
$router->get('security', 'SecurityController@index', 'security.manage');
$router->get('security/role', 'SecurityController@role', 'security.manage');
$router->post('security/role', 'SecurityController@saveRole', 'security.manage');
$router->post('security/role/reset', 'SecurityController@resetRole', 'security.manage');
$router->post('security/role/delete', 'SecurityController@deleteRole', 'security.manage');
$router->get('security/matrix', 'SecurityController@matrix', 'security.manage');
$router->post('security/matrix', 'SecurityController@saveMatrix', 'security.manage');
$router->get('security/settings', 'SecurityController@settings', 'app.configure');
$router->post('security/settings', 'SecurityController@saveSettings', 'app.configure');
$router->get('security/review', 'SecurityController@review', 'security.manage');

// Academic calendar
$router->get('calendar', 'CalendarController@index', 'calendar.view');
$router->get('calendar/list', 'CalendarController@listView', 'calendar.view');
$router->get('calendar/create', 'CalendarController@create', 'calendar.manage');
$router->post('calendar', 'CalendarController@store', 'calendar.manage');
$router->get('calendar/{id}', 'CalendarController@show', 'calendar.view');
$router->get('calendar/{id}/edit', 'CalendarController@edit', 'calendar.manage');
$router->post('calendar/{id}', 'CalendarController@update', 'calendar.manage');
$router->post('calendar/{id}/delete', 'CalendarController@destroy', 'calendar.manage');
$router->get('calendar/export.ics', 'CalendarController@ics', 'calendar.view');

// Admission
$router->get('admission', 'AdmissionController@index', 'admission.view');
$router->get('admission/create', 'AdmissionController@create', 'admission.manage');
$router->post('admission', 'AdmissionController@store', 'admission.manage');
$router->get('admission/{id}', 'AdmissionController@show', 'admission.view');
$router->get('admission/{id}/edit', 'AdmissionController@edit', 'admission.manage');
$router->post('admission/{id}', 'AdmissionController@update', 'admission.manage');
$router->post('admission/{id}/stage', 'AdmissionController@changeStage', 'admission.manage');
$router->post('admission/{id}/documents', 'AdmissionController@uploadDocument', 'admission.manage');
$router->get('admission/{id}/documents/{doc}', 'AdmissionController@document', 'admission.view');
$router->post('admission/{id}/enrol', 'AdmissionController@enrol', 'admission.enrol');

// Pupils
$router->get('pupils', 'PupilController@index', 'pupils.view');
$router->get('pupils/{id}', 'PupilController@show', 'pupils.view');
$router->get('pupils/{id}/photo', 'PupilController@photo', 'pupils.view');
$router->get('pupils/{id}/edit', 'PupilController@edit', 'pupils.manage');
$router->post('pupils/{id}', 'PupilController@update', 'pupils.manage');
$router->post('pupils/{id}/guardians', 'PupilController@addGuardian', 'pupils.manage');
$router->post('pupils/{id}/guardians/{guardian}', 'PupilController@updateGuardian', 'pupils.manage');
$router->post('pupils/{id}/guardians/{guardian}/remove', 'PupilController@removeGuardian', 'pupils.manage');
$router->post('pupils/{id}/status', 'PupilController@changeStatus', 'pupils.manage');

// Audit
$router->get('audit', 'AuditController@index', 'audit.view');

// ---- Sprint 2 ----------------------------------------------------------------

// Notifications & messages
$router->get('notifications', 'MessageController@inbox');
$router->get('notifications/{id}', 'MessageController@open');
$router->post('notifications/read-all', 'MessageController@readAll');
$router->get('messages', 'MessageController@index', 'messages.send');
$router->get('messages/compose', 'MessageController@compose', 'messages.send');
$router->post('messages', 'MessageController@send', 'messages.send');
$router->get('messages/outbox', 'MessageController@outbox', 'messages.send');
$router->post('messages/process', 'MessageController@processNow', 'messages.send');
$router->post('messages/{id}/cancel', 'MessageController@cancel', 'messages.manage');
$router->post('messages/{id}/retry', 'MessageController@retry', 'messages.manage');
$router->get('messages/templates', 'MessageController@templates', 'app.configure');
$router->post('messages/templates', 'MessageController@saveTemplate', 'app.configure');
$router->get('messages/settings', 'MessageController@settings', 'app.configure');
$router->post('messages/settings', 'MessageController@saveSettings', 'app.configure');
$router->post('messages/test', 'MessageController@test', 'app.configure');

// Attendance
$router->get('attendance', 'AttendanceController@index', 'attendance.view');
$router->get('attendance/register/{id}', 'AttendanceController@register', 'attendance.take');
$router->post('attendance/register/{id}', 'AttendanceController@saveRegister', 'attendance.take');
$router->get('attendance/class/{id}', 'AttendanceController@classReport', 'attendance.view');
$router->get('attendance/class/{id}/export', 'AttendanceController@exportClass', 'attendance.view');
$router->get('attendance/absentees', 'AttendanceController@absentees', 'attendance.view');
$router->post('attendance/staff/sign', 'AttendanceController@staffSign', 'staff');
$router->get('attendance/staff', 'AttendanceController@staffReport', 'staff_att.view');
$router->get('attendance/staff/summary', 'AttendanceController@staffSummary', 'staff_att.view');
$router->get('attendance/staff/summary/export', 'AttendanceController@exportStaff', 'staff_att.view');
$router->post('attendance/staff/settings', 'AttendanceController@saveStaffSettings', 'app.configure');

// Form builder
$router->get('forms', 'FormController@index', 'forms.manage');
$router->post('forms', 'FormController@store', 'forms.manage');
$router->get('forms/{id}', 'FormController@builder', 'forms.manage');
$router->post('forms/{id}', 'FormController@update', 'forms.manage');
$router->post('forms/{id}/fields', 'FormController@addField', 'forms.manage');
$router->post('forms/{id}/fields/{field}', 'FormController@updateField', 'forms.manage');
$router->post('forms/{id}/fields/{field}/move', 'FormController@moveField', 'forms.manage');
$router->post('forms/{id}/fields/{field}/delete', 'FormController@deleteField', 'forms.manage');
$router->post('forms/{id}/duplicate', 'FormController@duplicate', 'forms.manage');
$router->post('forms/{id}/share', 'FormController@share', 'messages.send');
$router->get('forms/{id}/responses', 'FormController@responses', 'forms.manage');
$router->get('forms/{id}/responses/export', 'FormController@export', 'forms.manage');
$router->get('forms/{id}/responses/{resp}', 'FormController@response', 'forms.manage');
$router->get('forms/{id}/responses/{resp}/file/{field}', 'FormController@file', 'forms.manage');
$router->get('f/{token}', 'FillController@show', null);
$router->post('f/{token}', 'FillController@submit', null);
$router->get('f/{token}/done', 'FillController@done', null);

// ---- Sprint 3: exams & report cards --------------------------------------------
$router->get('exams', 'ExamController@index', 'exams.enter');
$router->get('exams/sheet/{id}', 'ExamController@sheet', 'exams.enter');
$router->post('exams/sheet/{id}', 'ExamController@saveScores', 'exams.enter');
$router->post('exams/sheet/{id}/paste', 'ExamController@paste', 'exams.enter');
$router->post('exams/sheet/{id}/assessments', 'ExamController@addAssessment', 'exams.enter');
$router->post('exams/sheet/{id}/assessments/{aid}', 'ExamController@updateAssessment', 'exams.enter');
$router->post('exams/sheet/{id}/submit', 'ExamController@submit', 'exams.enter');
$router->post('exams/sheet/{id}/approve', 'ExamController@approve', 'exams.manage');
$router->post('exams/sheet/{id}/reopen', 'ExamController@reopen', 'exams.manage');
$router->get('exams/setup', 'ExamController@setup', 'app.configure');
$router->post('exams/setup/{id}', 'ExamController@saveScheme', 'app.configure');
$router->post('exams/setup/{id}/preset', 'ExamController@applyPreset', 'app.configure');
$router->post('exams/setup/report', 'ExamController@saveReportSettings', 'app.configure');
$router->get('exams/allocations', 'ExamController@allocations', 'exams.manage');
$router->post('exams/allocations/{id}', 'ExamController@saveAllocations', 'exams.manage');

$router->get('reports', 'ReportController@index', 'reports.view');
$router->get('reports/class/{id}/broadsheet', 'ReportController@broadsheet', 'reports.view');
$router->get('reports/class/{id}/broadsheet/export', 'ReportController@exportBroadsheet', 'reports.view');
$router->get('reports/class/{id}/remarks', 'ReportController@remarks', 'reports.view');
$router->post('reports/class/{id}/remarks', 'ReportController@saveRemarks', 'reports.view');
$router->get('reports/class/{id}/print', 'ReportController@printClass', 'reports.view');
$router->get('reports/class/{id}/personality', 'ReportController@personality', 'reports.view');
$router->post('reports/class/{id}/personality', 'ReportController@savePersonality', 'reports.view');
$router->post('reports/class/{id}/release', 'ReportController@release', 'exams.manage');
$router->post('reports/class/{id}/unrelease', 'ReportController@unrelease', 'exams.manage');
$router->get('reports/pupil/{id}', 'ReportController@pupil', 'reports.view');
$router->get('r/{token}', 'ReportController@shared', null);

// ---- Sprint 4: time table --------------------------------------------------------
$router->get('timetable', 'TimetableController@index', 'timetable.view');
$router->get('timetable/my', 'TimetableController@my', 'timetable.view');
$router->get('timetable/class/{id}', 'TimetableController@classView', 'timetable.view');
$router->post('timetable/class/{id}', 'TimetableController@saveClass', 'timetable.manage');
$router->get('timetable/teacher/{id}', 'TimetableController@teacher', 'timetable.view');
$router->post('timetable/generate', 'TimetableController@generate', 'timetable.manage');
$router->get('timetable/periods', 'TimetableController@periods', 'timetable.manage');
$router->post('timetable/periods', 'TimetableController@savePeriods', 'timetable.manage');
$router->get('timetable/lessons', 'TimetableController@lessons', 'timetable.manage');
$router->post('timetable/lessons/{id}', 'TimetableController@saveLessons', 'timetable.manage');
$router->get('timetable/check', 'TimetableController@check', 'timetable.manage');
$router->get('timetable/free', 'TimetableController@free', 'timetable.view');
$router->get('timetable/print', 'TimetableController@printAll', 'timetable.view');
$router->get('logo', 'SetupController@logo', null);
$router->post('setup/logo', 'SetupController@saveLogo', 'app.configure');

// ---- Sprint 5: fees & online payment ----------------------------------------------
$router->get('fees', 'FeeController@index', 'fees.view');
$router->get('fees/schedule', 'FeeController@schedule', 'fees.view');
$router->post('fees/schedule', 'FeeController@saveSchedule', 'app.configure');
$router->post('fees/items', 'FeeController@saveItem', 'app.configure');
$router->post('fees/items/{id}', 'FeeController@saveItem', 'app.configure');
$router->get('fees/discounts', 'FeeController@discounts', 'fees.view');
$router->post('fees/discounts', 'FeeController@saveDiscount', 'app.configure');
$router->post('fees/discounts/{id}', 'FeeController@saveDiscount', 'app.configure');
$router->get('fees/invoices', 'FeeController@invoices', 'fees.view');
$router->get('fees/invoices/{id}', 'FeeController@showInvoice', 'fees.view');
$router->get('fees/invoices/{id}/print', 'FeeController@printInvoice', 'fees.view');
$router->post('fees/invoices/{id}/lines', 'FeeController@addLine', 'fees.manage');
$router->post('fees/invoices/{id}/lines/{line}/delete', 'FeeController@deleteLine', 'fees.manage');
$router->post('fees/invoices/{id}/cancel', 'FeeController@cancel', 'fees.manage');
$router->post('fees/invoices/{id}/sms', 'FeeController@smsInvoice', 'fees.manage');
$router->post('fees/invoices/{id}/pay', 'FeeController@pay', 'fees.manage');
$router->get('fees/receipts/{id}', 'FeeController@receipt', 'fees.view');
$router->post('fees/payments/{id}/reverse', 'FeeController@reverse', 'fees.reverse');
$router->get('fees/generate', 'FeeController@generateForm', 'fees.manage');
$router->post('fees/generate', 'FeeController@generate', 'fees.manage');
$router->get('fees/pupil/{id}', 'FeeController@statement', 'fees.view');
$router->post('fees/pupil/{id}/invoice', 'FeeController@adhocInvoice', 'fees.manage');
$router->post('fees/pupil/{id}/discounts', 'FeeController@savePupilDiscounts', 'fees.manage');
$router->get('fees/debtors', 'FeeController@debtors', 'fees.view');
$router->post('fees/reminders', 'FeeController@reminders', 'fees.manage');
$router->get('fees/collections', 'FeeController@collections', 'fees.view');
$router->get('fees/daily', 'FeeController@daily', 'fees.view');
$router->get('fees/online', 'FeeController@online', 'fees.view');
$router->post('fees/online/{id}/check', 'FeeController@checkOnline', 'fees.view');
$router->get('fees/settings', 'FeeController@settings', 'app.configure');
$router->post('fees/settings', 'FeeController@saveSettings', 'app.configure');
// Family accounts, credit notes & refunds
$router->get('fees/families', 'FamilyController@index', 'fees.view');
$router->get('fees/family/{id}', 'FamilyController@show', 'fees.view');
$router->post('fees/family/{id}/pay', 'FamilyController@pay', 'fees.manage');
$router->post('fees/family/{id}/apply', 'FamilyController@apply', 'fees.manage');
$router->post('fees/family/{id}/refund', 'FamilyController@requestRefund', 'fees.manage');
$router->post('fees/family/{id}/advance/{entry}/reverse', 'FamilyController@reverseAdvance', 'fees.reverse');
$router->get('fees/family-receipt', 'FamilyController@receipt', 'fees.view');
$router->post('fees/invoices/{id}/use-credit', 'FamilyController@applyBill', 'fees.manage');
$router->post('fees/invoices/{id}/to-credit', 'FamilyController@toCredit', 'fees.manage');
$router->post('fees/invoices/{id}/credit-note', 'FamilyController@requestCreditNote', 'fees.manage');
$router->post('fees/credit-notes/{id}/decide', 'FamilyController@decideCreditNote', 'fees.approve');
$router->get('fees/credit-notes/{id}/print', 'FamilyController@printCreditNote', 'fees.view');
$router->get('fees/refunds', 'FamilyController@refunds', 'fees.view');
$router->post('fees/refunds/{id}/decide', 'FamilyController@decideRefund', 'fees.approve');
$router->post('fees/refunds/{id}/pay', 'FamilyController@payRefund', 'fees.manage');
$router->get('fees/refunds/{id}/print', 'FamilyController@printRefund', 'fees.view');

// Parent pay page (link in SMS) and gateway callbacks
$router->get('pay/test', 'PayController@testPage', null);
$router->post('pay/test', 'PayController@testDecide', null);
$router->get('pay/{token}', 'PayController@show', null);
$router->get('verify', 'VerifyController@show', null);
$router->get('verify/{token}', 'VerifyController@show', null);
$router->post('pay/{token}', 'PayController@start', null);
$router->get('pay/{token}/return', 'PayController@returned', null);
$router->post('webhooks/paystack', 'PayController@paystackHook', 'webhook');
$router->post('webhooks/hubtel', 'PayController@hubtelHook', 'webhook');

// ---- Sprint 6: finance and requisitions -------------------------------------------
$router->get('finance', 'FinanceController@index', 'finance.view');
$router->get('finance/dashboard', 'FinanceController@dashboard', 'finance.view');
$router->get('finance/transactions', 'FinanceController@transactions', 'finance.view');
$router->get('finance/new', 'FinanceController@create', 'finance.manage');
$router->post('finance/transactions', 'FinanceController@store', 'finance.manage');
$router->get('finance/transactions/{id}', 'FinanceController@show', 'finance.view');
$router->get('finance/transactions/{id}/file', 'FinanceController@file', 'finance.view');
$router->post('finance/transactions/{id}/void', 'FinanceController@void', 'finance.void');
$router->get('finance/cashbook', 'FinanceController@cashbook', 'finance.view');
$router->get('finance/statement', 'FinanceController@statement', 'finance.view');
$router->get('finance/budget', 'FinanceController@budget', 'finance.view');
$router->post('finance/budget', 'FinanceController@saveBudget', 'finance.budget');
$router->get('finance/budget/departments', 'FinanceController@departments', 'finance.view');
$router->post('finance/budget/departments', 'FinanceController@saveDepartments', 'budget.caps');
$router->post('finance/budget/departments/move', 'FinanceController@moveCap', 'budget.caps');
$router->post('finance/budget/departments/list', 'FinanceController@saveDepartmentList', 'budget.caps');
$router->get('finance/budget/reserves', 'FinanceController@reserves', 'finance.view');
$router->post('finance/budget/reserves', 'FinanceController@saveReserves', 'finance.budget');
$router->get('finance/setup', 'FinanceController@setup', 'app.configure');
// Petty cash (custodians are checked inside the controller)
$router->get('petty-cash', 'PettyController@index', 'staff');
$router->post('petty-cash', 'PettyController@store', 'finance.manage');
$router->get('petty-cash/{id}', 'PettyController@show', 'staff');
$router->post('petty-cash/{id}', 'PettyController@update', 'finance.manage');
$router->post('petty-cash/{id}/spend', 'PettyController@spend', 'staff');
$router->post('petty-cash/{id}/void/{txn}', 'PettyController@voidSpend', 'finance.manage');
$router->post('petty-cash/{id}/topup', 'PettyController@topup', 'finance.manage');
$router->get('petty-cash/{id}/print', 'PettyController@printDoc', 'staff');
$router->get('petty-cash/{id}/file/{txn}', 'PettyController@file', 'staff');
// Fixed assets
$router->get('fixed-assets', 'AssetController@index', 'finance.view');
$router->get('fixed-assets/new', 'AssetController@create', 'assets.manage');
$router->post('fixed-assets', 'AssetController@store', 'assets.manage');
$router->get('fixed-assets/print', 'AssetController@printout', 'finance.view');
$router->get('fixed-assets/{id}', 'AssetController@show', 'finance.view');
$router->post('fixed-assets/{id}', 'AssetController@update', 'assets.manage');
$router->post('fixed-assets/{id}/dispose', 'AssetController@dispose', 'assets.manage');
// Month-end close & board pack
$router->get('finance/close', 'CloseController@index', 'finance.view');
$router->post('finance/close', 'CloseController@close', 'finance.close');
$router->post('finance/close/reopen', 'CloseController@reopen', 'finance.reopen');
$router->get('finance/board-pack', 'CloseController@boardPack', 'finance.view');
$router->get('finance/reports', 'FinanceReportController@index', 'finance.view');
// The books (double entry)
$router->get('ledger', 'LedgerController@index', 'finance.view');
$router->get('ledger/accounts', 'LedgerController@accounts', 'finance.view');
$router->post('ledger/accounts', 'LedgerController@saveAccount', 'finance.manage');
$router->get('ledger/accounts/{id}', 'LedgerController@account', 'finance.view');
$router->post('ledger/accounts/{id}', 'LedgerController@saveAccount', 'finance.manage');
$router->get('ledger/journal', 'LedgerController@journal', 'finance.view');
$router->get('ledger/entries/{id}', 'LedgerController@entry', 'finance.view');
$router->get('ledger/trial-balance', 'LedgerController@trialBalance', 'finance.view');
$router->get('ledger/balance-sheet', 'LedgerController@balanceSheet', 'finance.view');
$router->get('ledger/journals', 'LedgerController@journals', 'finance.view');
$router->post('ledger/journals', 'LedgerController@storeJournal', 'ledger.journal');
$router->get('ledger/journals/{id}', 'LedgerController@showJournal', 'finance.view');
$router->post('ledger/journals/{id}/decide', 'LedgerController@decideJournal', 'ledger.approve');
// Suppliers & bills to pay
// Purchasing: purchase orders, goods received, three-way match
$router->get('purchasing', 'PurchasingController@index', 'staff');
$router->get('purchasing/new', 'PurchasingController@create', 'purchasing.order');
$router->post('purchasing', 'PurchasingController@store', 'purchasing.order');
$router->get('purchasing/received', 'PurchasingController@received', 'staff');
$router->get('purchasing/{id}', 'PurchasingController@show', 'staff');
$router->get('purchasing/{id}/print', 'PurchasingController@printPo', 'staff');
$router->post('purchasing/{id}/send', 'PurchasingController@send', 'purchasing.order');
$router->post('purchasing/{id}/cancel', 'PurchasingController@cancel', 'purchasing.order');
$router->post('purchasing/{id}/close', 'PurchasingController@close', 'purchasing.order');
$router->post('purchasing/{id}/receive', 'PurchasingController@receive', 'purchasing.receive');
$router->post('purchasing/{id}/bill', 'PurchasingController@bill', 'purchasing.order');
$router->get('purchasing/grn/{id}', 'PurchasingController@printGrn', 'staff');
$router->post('purchasing/grn/{id}/void', 'PurchasingController@voidGrn', 'purchasing.order');
$router->post('suppliers/bills/{id}/approve', 'SupplierController@approveBill', 'purchasing.approve');
$router->get('suppliers', 'SupplierController@index', 'finance.view');
$router->get('suppliers/bills/new', 'SupplierController@newBill', 'finance.manage');
$router->post('suppliers/bills', 'SupplierController@storeBill', 'finance.manage');
$router->get('suppliers/bills/{id}', 'SupplierController@showBill', 'finance.view');
$router->post('suppliers/bills/{id}/pay', 'SupplierController@payBill', 'finance.manage');
$router->post('suppliers/bills/{id}/cancel', 'SupplierController@cancelBill', 'finance.manage');
$router->get('suppliers/bills/{id}/file', 'SupplierController@billFile', 'finance.view');
$router->post('suppliers/payments/{id}/void', 'SupplierController@voidPayment', 'finance.void');
$router->get('suppliers/payments/{id}/voucher', 'SupplierController@voucher', 'finance.view');
$router->get('suppliers/list', 'SupplierController@suppliers', 'finance.view');
$router->post('suppliers/list', 'SupplierController@saveSupplier', 'finance.manage');
$router->post('suppliers/list/{id}', 'SupplierController@saveSupplier', 'finance.manage');
$router->get('suppliers/wht', 'SupplierController@wht', 'finance.view');
$router->post('suppliers/wht/remit', 'SupplierController@remit', 'finance.manage');
$router->post('suppliers/wht/rates', 'SupplierController@rates', 'app.configure');
$router->get('suppliers/wht/{id}/print', 'SupplierController@remitPrint', 'finance.view');
$router->get('finance/reconcile', 'ReconcileController@index', 'finance.view');
$router->get('finance/reconcile/{id}', 'ReconcileController@show', 'finance.manage');
$router->post('finance/reconcile/{id}', 'ReconcileController@save', 'finance.manage');
$router->get('finance/reconcile/report/{id}', 'ReconcileController@report', 'finance.view');
$router->post('finance/reconcile/report/{id}/reopen', 'ReconcileController@reopen', 'finance.manage');
$router->post('finance/accounts', 'FinanceController@saveAccount', 'app.configure');
$router->post('finance/accounts/{id}', 'FinanceController@saveAccount', 'app.configure');
$router->post('finance/categories', 'FinanceController@saveCategory', 'app.configure');
$router->post('finance/categories/{id}', 'FinanceController@saveCategory', 'app.configure');
$router->post('finance/settings', 'FinanceController@saveSettings', 'app.configure');

$router->get('requisitions', 'RequisitionController@index', 'requisitions.create');
$router->get('requisitions/create', 'RequisitionController@create', 'requisitions.create');
$router->post('requisitions', 'RequisitionController@store', 'requisitions.create');
$router->get('requisitions/{id}', 'RequisitionController@show', 'requisitions.create');
$router->get('requisitions/{id}/print', 'RequisitionController@printForm', 'requisitions.create');
$router->get('requisitions/{id}/edit', 'RequisitionController@edit', 'requisitions.create');
$router->post('requisitions/{id}', 'RequisitionController@update', 'requisitions.create');
$router->post('requisitions/{id}/submit', 'RequisitionController@submit', 'requisitions.create');
$router->post('requisitions/{id}/cancel', 'RequisitionController@cancel', 'requisitions.create');
$router->post('requisitions/{id}/decide', 'RequisitionController@decide', 'requisitions.create');
$router->post('requisitions/{id}/pay', 'RequisitionController@pay', 'requisitions.pay');
$router->post('requisitions/{id}/retire', 'RequisitionController@retire', 'requisitions.pay');
$router->post('requisitions/{id}/files', 'RequisitionController@upload', 'requisitions.create');
$router->get('requisitions/{id}/files/{file}', 'RequisitionController@file', 'requisitions.create');

// ---- Sprint 7: communication, quizzes, promotion -----------------------------------
$router->get('logins', 'LoginController@index', 'logins.manage');
$router->post('logins/parents', 'LoginController@parents', 'logins.manage');
$router->post('logins/pupils', 'LoginController@pupils', 'logins.manage');
$router->get('logins/slips', 'LoginController@slips', 'logins.manage');
$router->post('logins/users/{id}/toggle', 'LoginController@toggle', 'logins.manage');

$router->get('portal', 'PortalController@index', 'portal.parent');
$router->get('portal/child/{id}', 'PortalController@child', 'portal.parent');
$router->get('student', 'StudentPortalController@index', 'chat.use');
$router->post('student/profile', 'StudentPortalController@updateProfile', 'chat.use');

$router->get('notices', 'NoticeController@index', 'notices.view');
$router->get('login/notices/{id}/file', 'NoticeController@publicFile', null); // sign-in page notices only
$router->get('notices/create', 'NoticeController@create', 'notices.manage');
$router->post('notices', 'NoticeController@store', 'notices.manage');
$router->get('notices/{id}', 'NoticeController@show', 'notices.view');
$router->get('notices/{id}/file', 'NoticeController@file', 'notices.view');
$router->post('notices/{id}/ack', 'NoticeController@acknowledge', 'notices.view');
$router->get('notices/{id}/edit', 'NoticeController@edit', 'notices.manage');
$router->post('notices/{id}', 'NoticeController@update', 'notices.manage');
$router->post('notices/{id}/delete', 'NoticeController@destroy', 'notices.manage');

$router->get('conversations', 'ConversationController@index', 'chat.use');
$router->get('conversations/create', 'ConversationController@create', 'chat.use');
$router->post('conversations', 'ConversationController@store', 'chat.use');
$router->get('conversations/{id}', 'ConversationController@show', 'chat.use');
$router->post('conversations/{id}/reply', 'ConversationController@reply', 'chat.use');
$router->post('conversations/{id}/close', 'ConversationController@close', 'chat.use');

$router->get('quizzes', 'QuizController@index', 'quiz.manage');
$router->post('quizzes', 'QuizController@store', 'quiz.manage');
$router->get('quizzes/{id}', 'QuizController@edit', 'quiz.manage');
$router->post('quizzes/{id}', 'QuizController@update', 'quiz.manage');
$router->post('quizzes/{id}/questions', 'QuizController@saveQuestion', 'quiz.manage');
$router->post('quizzes/{id}/questions/{qid}', 'QuizController@saveQuestion', 'quiz.manage');
$router->post('quizzes/{id}/questions/{qid}/delete', 'QuizController@deleteQuestion', 'quiz.manage');
$router->post('quizzes/{id}/questions/{qid}/move', 'QuizController@moveQuestion', 'quiz.manage');
$router->post('quizzes/{id}/status', 'QuizController@status', 'quiz.manage');
$router->get('quizzes/{id}/results', 'QuizController@results', 'quiz.manage');
$router->get('quizzes/{id}/attempts/{aid}', 'QuizController@attempt', 'quiz.manage');
$router->post('quizzes/{id}/to-assessment', 'QuizController@toAssessment', 'quiz.manage');
$router->post('quizzes/{id}/delete', 'QuizController@destroy', 'quiz.manage');

$router->get('learn', 'LearnController@index', 'quiz.take');
$router->post('learn/{id}/start', 'LearnController@start', 'quiz.take');
$router->get('learn/attempt/{aid}', 'LearnController@take', 'quiz.take');
$router->post('learn/attempt/{aid}/save', 'LearnController@save', 'quiz.take');
$router->post('learn/attempt/{aid}/submit', 'LearnController@submit', 'quiz.take');
$router->get('learn/attempt/{aid}/result', 'LearnController@result', 'quiz.take');

$router->get('promotion', 'PromotionController@index', 'promotion.manage');
$router->get('promotion/class/{id}', 'PromotionController@classView', 'promotion.manage');
$router->post('promotion/class/{id}', 'PromotionController@apply', 'promotion.manage');
$router->post('promotion/class/{id}/undo', 'PromotionController@undo', 'promotion.manage');

// ---- Sprint 8: go-live --------------------------------------------------------------
$router->get('help', 'HelpController@index');
$router->get('backups', 'BackupController@index', 'backups.manage');
$router->post('backups', 'BackupController@create', 'backups.manage');
$router->get('backups/{id}/download', 'BackupController@download', 'backups.manage');
$router->post('backups/settings', 'BackupController@settings', 'backups.manage');
$router->get('imports', 'ImportController@index', 'imports.manage');
$router->get('imports/template', 'ImportController@template', 'imports.manage');
$router->post('imports', 'ImportController@upload', 'imports.manage');
$router->get('imports/preview', 'ImportController@preview', 'imports.manage');
$router->post('imports/commit', 'ImportController@commit', 'imports.manage');
$router->post('imports/cancel', 'ImportController@cancel', 'imports.manage');
$router->get('golive', 'GoLiveController@index', 'golive.manage');
$router->post('golive/tick', 'GoLiveController@tick', 'golive.manage');

// ---- Sprint 9: payroll, library, transport, health, BECE -----------------------------
$router->get('payroll', 'PayrollController@index', 'payroll.view');
$router->post('payroll/settings', 'PayrollController@settings', 'app.configure');
$router->get('payroll/staff', 'PayrollController@staff', 'payroll.view');
$router->get('payroll/staff/create', 'PayrollController@createStaff', 'payroll.manage');
$router->post('payroll/staff', 'PayrollController@storeStaff', 'payroll.manage');
$router->get('payroll/staff/{id}', 'PayrollController@editStaff', 'payroll.manage');
$router->post('payroll/staff/{id}', 'PayrollController@updateStaff', 'payroll.manage');
$router->post('payroll/staff/{id}/items', 'PayrollController@addItem', 'payroll.manage');
$router->post('payroll/staff/{id}/items/{iid}/delete', 'PayrollController@deleteItem', 'payroll.manage');
$router->post('payroll/runs', 'PayrollController@createRun', 'payroll.manage');
$router->get('payroll/runs/{id}', 'PayrollController@showRun', 'payroll.view');
$router->post('payroll/runs/{id}/rebuild', 'PayrollController@rebuild', 'payroll.manage');
$router->post('payroll/runs/{id}/delete', 'PayrollController@destroyRun', 'payroll.manage');
$router->post('payroll/runs/{id}/approve', 'PayrollController@approve', 'payroll.approve');
$router->post('payroll/runs/{id}/reopen', 'PayrollController@reopen', 'payroll.approve');
$router->post('payroll/runs/{id}/pay', 'PayrollController@pay', 'payroll.manage');
$router->post('payroll/runs/{id}/statutory', 'PayrollController@statutory', 'payroll.manage');
$router->get('payroll/runs/{id}/slips', 'PayrollController@slips', 'payroll.view');
$router->get('payroll/runs/{id}/schedule', 'PayrollController@schedule', 'payroll.view');
$router->get('my/payslips', 'PayrollController@mine', 'payslips.own');
$router->get('my/payslips/{id}', 'PayrollController@mySlip', 'payslips.own');

$router->get('library', 'LibraryController@index', 'library.view');
$router->get('library/report', 'LibraryController@report', 'library.view');
$router->post('library/remind', 'LibraryController@remind', 'library.manage');
$router->post('library/issue', 'LibraryController@issue', 'library.manage');
$router->get('library/books', 'LibraryController@books', 'library.view');
$router->get('library/books/create', 'LibraryController@create', 'library.manage');
$router->post('library/books', 'LibraryController@store', 'library.manage');
$router->get('library/books/{id}', 'LibraryController@show', 'library.view');
$router->post('library/books/{id}', 'LibraryController@update', 'library.manage');
$router->post('library/loans/{id}/return', 'LibraryController@returnLoan', 'library.manage');
$router->post('library/loans/{id}/renew', 'LibraryController@renew', 'library.manage');
$router->post('library/loans/{id}/lost', 'LibraryController@lost', 'library.manage');

$router->get('transport', 'TransportController@index', 'transport.view');
$router->post('transport/bill', 'TransportController@bill', 'fees.manage');
$router->get('transport/routes/create', 'TransportController@create', 'transport.manage');
$router->post('transport/routes', 'TransportController@store', 'transport.manage');
$router->get('transport/routes/{id}', 'TransportController@show', 'transport.view');
$router->post('transport/routes/{id}', 'TransportController@update', 'transport.manage');
$router->post('transport/routes/{id}/assign', 'TransportController@assign', 'transport.manage');
$router->get('transport/routes/{id}/print', 'TransportController@manifest', 'transport.view');
$router->post('transport/assignments/{id}/end', 'TransportController@endAssignment', 'transport.manage');

$router->get('health', 'HealthController@index', 'health.view');
$router->post('health/visits', 'HealthController@storeVisit', 'health.manage');
$router->get('health/report', 'HealthController@report', 'health.view');
$router->get('health/pupils/{id}', 'HealthController@pupil', 'health.view');
$router->post('health/pupils/{id}', 'HealthController@savePupil', 'health.manage');

$router->get('bece', 'BeceController@index', 'bece.view');
$router->post('bece', 'BeceController@save', 'bece.manage');
$router->get('bece/export', 'BeceController@export', 'bece.view');
$router->get('bece/photos', 'BeceController@photos', 'bece.view');
$router->get('bece/print', 'BeceController@printList', 'bece.view');
$router->get('bece/results', 'BeceController@results', 'bece.view');
$router->post('bece/results', 'BeceController@saveResults', 'bece.manage');

// ---- Sprint 10: setup wizard, reports, extras, demo ------------------------------------
$router->get('setup/wizard', 'WizardController@index', 'app.configure');
$router->post('setup/wizard', 'WizardController@run', 'app.configure');
$router->post('messages/balance', 'WizardController@smsBalance', 'app.configure');
$router->post('fees/settings/test', 'WizardController@paymentTest', 'app.configure');
$router->get('analytics', 'AnalyticsController@index', 'analytics.view');
$router->get('pupils/class-list', 'PupilDocsController@classList', 'pupils.view');
$router->get('pupils/id-cards', 'PupilDocsController@idCards', 'pupils.view');
$router->get('pupils/{id}/letter', 'PupilDocsController@letter', 'pupils.manage');
$router->get('attendance/class/{id}/month', 'PupilDocsController@monthRegister', 'attendance.view');
$router->get('payroll/annual', 'PayrollController@annual', 'payroll.view');
$router->get('practice', 'PracticeController@index', 'users.manage');
$router->post('practice', 'PracticeController@build', 'users.manage');
$router->post('practice/remove', 'PracticeController@remove', 'users.manage');
$router->post('quick-add', 'QuickAddController@store');
$router->post('export/table', 'ExportController@table', 'staff'); // on-screen lists as Excel / Word / PDF
$router->get('bg-image', 'SetupController@bgImage', null);
$router->post('setup/background', 'SetupController@saveBackground', 'app.configure');

// ---- Sprint 16: teachers, curriculum & classrooms ----------------------------------------
$router->get('teachers', 'TeacherController@index', 'teachers.view');
$router->get('teacher-profile/{id}', 'TeacherController@portalProfile');
$router->get('teacher-records', 'TeacherRecordController@index', 'teachers.profile.manage');
$router->get('teacher-records/create', 'TeacherRecordController@create', 'teachers.profile.manage');
$router->post('teacher-records', 'TeacherRecordController@store', 'teachers.profile.manage');
$router->get('teacher-records/{id}/edit', 'TeacherRecordController@edit', 'teachers.profile.manage');
$router->post('teacher-records/{id}', 'TeacherRecordController@update', 'teachers.profile.manage');
$router->post('teacher-records/{id}/archive', 'TeacherRecordController@archive', 'teachers.profile.manage');
$router->get('teachers/me', 'TeacherController@me', 'staff');
$router->get('teachers/criteria', 'TeacherController@criteria', 'appraisal.manage');
$router->post('teachers/criteria', 'TeacherController@saveCriteria', 'appraisal.manage');
$router->post('teachers/appraisals/open', 'TeacherController@openAll', 'appraisal.manage');
$router->get('teachers/{id}', 'TeacherController@show', 'staff');
$router->get('teachers/{id}/appraisal', 'TeacherController@appraisal', 'staff');
$router->get('teachers/{id}/appraisal/print', 'TeacherController@printAppraisal', 'staff');
$router->post('teachers/{id}/appraisal/start', 'TeacherController@start', 'appraisal.manage');
$router->post('teachers/{id}/appraisal/self', 'TeacherController@saveSelf', 'staff');
$router->post('teachers/{id}/appraisal/head', 'TeacherController@saveHead', 'appraisal.manage');
$router->post('teachers/{id}/appraisal/ack', 'TeacherController@acknowledge', 'staff');
$router->post('teachers/{id}/appraisal/reopen', 'TeacherController@reopen', 'appraisal.manage');

$router->get('curriculum', 'CurriculumController@index', 'scheme.plan');
$router->get('curriculum/strands', 'CurriculumController@strands', 'scheme.plan');
$router->get('curriculum/performance', 'SubjectController@performance', 'scheme.plan');
$router->get('curriculum/materials', 'SubjectController@materials', 'scheme.plan');
$router->post('curriculum/materials', 'SubjectController@saveMaterial', 'curriculum.manage');
$router->post('curriculum/materials/{id}', 'SubjectController@saveMaterial', 'curriculum.manage');
$router->post('curriculum/materials/{id}/delete', 'SubjectController@deleteMaterial', 'curriculum.manage');
$router->get('lesson-notes', 'LessonNoteController@index', 'scheme.plan');
$router->get('lesson-notes/write', 'LessonNoteController@write', 'scheme.plan');
$router->post('lesson-notes/save', 'LessonNoteController@save', 'scheme.plan');
$router->post('lesson-notes/remind', 'LessonNoteController@remind', 'lessonnotes.vet');
$router->get('lesson-notes/{id}', 'LessonNoteController@show', 'scheme.plan');
$router->get('lesson-notes/{id}/print', 'LessonNoteController@printNote', 'scheme.plan');
$router->post('lesson-notes/{id}/vet', 'LessonNoteController@vet', 'lessonnotes.vet');
$router->post('curriculum/strands', 'CurriculumController@saveStrands', 'curriculum.manage');
$router->get('curriculum/scheme/{id}', 'CurriculumController@scheme', 'scheme.plan');
$router->get('curriculum/scheme/{id}/print', 'CurriculumController@printScheme', 'scheme.plan');
$router->post('curriculum/scheme/{id}/add', 'CurriculumController@addItem', 'scheme.plan');
$router->post('curriculum/scheme/{id}/save', 'CurriculumController@saveItems', 'scheme.plan');
$router->post('curriculum/scheme/{id}/draft', 'CurriculumController@draft', 'scheme.plan');
$router->post('curriculum/scheme/{id}/copy', 'CurriculumController@copy', 'scheme.plan');
$router->post('curriculum/scheme/item/{id}/tick', 'CurriculumController@tick', 'scheme.plan');

$router->get('seating', 'SeatingController@index', 'seating.manage');
$router->get('seating/class/{id}', 'SeatingController@classPlan', 'seating.manage');
$router->post('seating/class/{id}', 'SeatingController@createClassPlan', 'seating.manage');
$router->get('seating/exam/create', 'SeatingController@createExam', 'examtt.manage');
$router->post('seating/exam', 'SeatingController@storeExam', 'examtt.manage');
$router->get('seating/{id}', 'SeatingController@show', 'staff');
$router->get('seating/{id}/print', 'SeatingController@printPlan', 'staff');
$router->post('seating/{id}/save', 'SeatingController@save', 'staff');
$router->post('seating/{id}/swap', 'SeatingController@swap', 'staff');
$router->post('seating/{id}/rearrange', 'SeatingController@rearrange', 'staff');
$router->post('seating/{id}/delete', 'SeatingController@destroy', 'staff');

$router->get('resources', 'ResourceController@index', 'resources.view');
$router->get('resources/print', 'ResourceController@printReport', 'resources.view');
$router->post('resources/rooms', 'ResourceController@storeRoom', 'resources.manage');
$router->post('resources/rooms/for-classes', 'ResourceController@roomsForClasses', 'resources.manage');
$router->get('resources/rooms/{id}', 'ResourceController@room', 'resources.view');
$router->post('resources/rooms/{id}', 'ResourceController@updateRoom', 'resources.manage');
$router->post('resources/rooms/{id}/delete', 'ResourceController@destroyRoom', 'resources.manage');
$router->post('resources/rooms/{id}/items', 'ResourceController@addItem', 'resources.manage');
$router->post('resources/rooms/{id}/items/save', 'ResourceController@saveItems', 'resources.manage');
$router->post('resources/rooms/{id}/standard', 'ResourceController@addStandard', 'resources.manage');

$router->get('exam-timetable', 'ExamTimetableController@index', 'timetable.view');
$router->post('exam-timetable', 'ExamTimetableController@store', 'examtt.manage');
$router->get('exam-timetable/{id}', 'ExamTimetableController@show', 'timetable.view');
$router->post('exam-timetable/{id}', 'ExamTimetableController@update', 'examtt.manage');
$router->get('exam-timetable/{id}/print', 'ExamTimetableController@printView', 'timetable.view');
$router->post('exam-timetable/{id}/delete', 'ExamTimetableController@destroy', 'examtt.manage');
$router->post('exam-timetable/{id}/papers', 'ExamTimetableController@addPaper', 'examtt.manage');
$router->post('exam-timetable/{id}/publish', 'ExamTimetableController@publish', 'examtt.manage');
$router->post('exam-timetable/{id}/unpublish', 'ExamTimetableController@unpublish', 'examtt.manage');
$router->get('exam-timetable/papers/{id}/edit', 'ExamTimetableController@editPaper', 'examtt.manage');
$router->post('exam-timetable/papers/{id}', 'ExamTimetableController@updatePaper', 'examtt.manage');
$router->post('exam-timetable/papers/{id}/delete', 'ExamTimetableController@destroyPaper', 'examtt.manage');

// ---- Sprint 17: assignments, pupil leave, staff timesheets & shifts, modules page --------------
$router->get('modules', 'ModuleController@index');
$router->get('modules/guide', 'ModuleController@guide');
$router->get('modules/{slug}', 'ModuleController@show');
$router->get('assignments', 'AssignmentController@index', 'assignments.manage');
$router->get('assignments/create', 'AssignmentController@create', 'assignments.manage');
$router->post('assignments', 'AssignmentController@store', 'assignments.manage');
$router->get('assignments/{id}', 'AssignmentController@show', 'assignments.manage');
$router->get('assignments/{id}/edit', 'AssignmentController@edit', 'assignments.manage');
$router->post('assignments/{id}', 'AssignmentController@update', 'assignments.manage');
$router->post('assignments/{id}/status', 'AssignmentController@status', 'assignments.manage');
$router->post('assignments/{id}/mark/{pupil}', 'AssignmentController@mark', 'assignments.manage');
$router->get('assignments/{id}/file', 'AssignmentController@file');
$router->get('assignments/{id}/work/{sub}', 'AssignmentController@subFile');
$router->get('homework', 'AssignmentController@homework', 'assignments.submit');
$router->get('homework/{id}', 'AssignmentController@homeworkShow', 'assignments.submit');
$router->post('homework/{id}/submit', 'AssignmentController@submit', 'assignments.submit');
$router->get('leave', 'LeaveController@index');
$router->get('leave/create', 'LeaveController@create');
$router->post('leave', 'LeaveController@store');
$router->get('leave/{id}', 'LeaveController@show');
$router->post('leave/{id}/decide', 'LeaveController@decide', 'leave.approve');
$router->post('leave/{id}/cancel', 'LeaveController@cancel');
$router->get('leave/{id}/file', 'LeaveController@file');
$router->get('timesheets', 'TimesheetController@index', 'timesheets.manage');
$router->get('timesheets/my', 'TimesheetController@my', 'staff');
$router->get('timesheets/shifts', 'TimesheetController@shifts', 'timesheets.manage');
$router->post('timesheets/shifts', 'TimesheetController@saveShift', 'timesheets.manage');
$router->post('timesheets/shifts/assign', 'TimesheetController@assign', 'timesheets.manage');
$router->get('timesheets/{id}', 'TimesheetController@show', 'timesheets.manage');
$router->post('timesheets/{id}/approve', 'TimesheetController@approve', 'timesheets.manage');
$router->post('timesheets/{id}/correct', 'TimesheetController@correct', 'timesheets.manage');

// ---- Sprint 18: canteen, events, online appointments -------------------------------------------
$router->get('canteen', 'CanteenController@index', 'canteen.sell');
$router->get('canteen/sell', 'CanteenController@sellForm', 'canteen.sell');
$router->post('canteen/sell', 'CanteenController@sell', 'canteen.sell');
$router->post('canteen/sales/{id}/void', 'CanteenController@void', 'canteen.manage');
$router->get('canteen/wallets', 'CanteenController@wallets', 'canteen.sell');
$router->get('canteen/wallets/{pupil}', 'CanteenController@wallet', 'canteen.sell');
$router->post('canteen/wallets/{pupil}/topup', 'CanteenController@topup', 'canteen.sell');
$router->post('canteen/wallets/{pupil}/limit', 'CanteenController@limit', 'canteen.sell');
$router->get('canteen/items', 'CanteenController@items', 'canteen.manage');
$router->post('canteen/items', 'CanteenController@saveItem', 'canteen.manage');
$router->post('canteen/items/{id}/stock', 'CanteenController@stock', 'canteen.manage');
$router->get('canteen/menu', 'CanteenController@menu', 'canteen.sell');
$router->post('canteen/menu', 'CanteenController@saveMenu', 'canteen.manage');
$router->get('canteen/plans', 'CanteenController@plans', 'canteen.manage');
$router->post('canteen/plans/save', 'CanteenController@savePlan', 'canteen.manage');
$router->post('canteen/plans/members', 'CanteenController@members', 'canteen.manage');
$router->post('canteen/plans/bill', 'CanteenController@bill', 'canteen.manage');
$router->get('canteen/served', 'CanteenController@served', 'canteen.sell');
$router->post('canteen/served', 'CanteenController@saveServed', 'canteen.sell');
$router->get('canteen/report', 'CanteenController@report', 'canteen.manage');
$router->get('events', 'EventController@index');
$router->get('events/create', 'EventController@create', 'events.manage');
$router->post('events', 'EventController@store', 'events.manage');
$router->get('events/{id}', 'EventController@show');
$router->get('events/{id}/edit', 'EventController@edit', 'events.manage');
$router->post('events/{id}', 'EventController@update', 'events.manage');
$router->post('events/{id}/status', 'EventController@status', 'events.manage');
$router->post('events/{id}/register', 'EventController@register');
$router->post('events/{id}/registrations/{rid}/cancel', 'EventController@cancelReg');
$router->post('events/{id}/checkin/{rid}', 'EventController@checkin', 'events.manage');
$router->get('events/{id}/export', 'EventController@export', 'events.manage');
$router->get('appointments', 'AppointmentController@index');
$router->get('appointments/book', 'AppointmentController@book', 'appointments.book');
$router->post('appointments', 'AppointmentController@store', 'appointments.book');
$router->post('appointments/{id}/cancel', 'AppointmentController@cancel');
$router->post('appointments/{id}/status', 'AppointmentController@status', 'appointments.offer');
$router->get('appointments/availability', 'AppointmentController@availability', 'appointments.offer');
$router->post('appointments/availability', 'AppointmentController@addAvailability', 'appointments.offer');
$router->post('appointments/availability/{id}/delete', 'AppointmentController@removeAvailability', 'appointments.offer');
$router->get('appointments/types', 'AppointmentController@types', 'appointments.manage');
$router->post('appointments/types', 'AppointmentController@saveType', 'appointments.manage');

// ---- Sprint 19: WhatsApp, phone app (PWA + push), Admission List, instalments ----------------------
$router->post('messages/whatsapp', 'WhatsappController@settings', 'app.configure');
$router->get('webhooks/whatsapp', 'WhatsappController@verify', null);
$router->post('webhooks/whatsapp', 'WhatsappController@hook', 'webhook');
$router->post('fees/invoices/{id}/plan', 'InstalmentController@save', 'fees.manage');
$router->get('manifest.webmanifest', 'PwaController@manifest', null);
$router->get('sw.js', 'PwaController@worker', null);
$router->get('offline', 'PwaController@offline', null);
$router->get('.well-known/assetlinks.json', 'PwaController@assetlinks', null);
$router->get('push/key', 'PwaController@key');
$router->post('push/subscribe', 'PwaController@subscribe');
$router->post('push/unsubscribe', 'PwaController@unsubscribe');
$router->post('push/test', 'PwaController@test');
$router->get('setup/app', 'PwaController@appSettings', 'app.configure');
$router->post('setup/app', 'PwaController@saveApp', 'app.configure');
$router->post('setup/app/invite', 'PwaController@invite', 'messages.send');
$router->post('setup/app/devices/{id}/remove', 'PwaController@removeDevice', 'app.configure');
$router->get('admission/merit', 'MeritController@index', 'admission.view');
$router->post('admission/merit/scores', 'MeritController@scores', 'admission.manage');
$router->post('admission/merit/offer', 'MeritController@offer', 'admission.manage');
$router->get('notices/{id}/reads.csv', 'NoticeController@readsCsv', 'notices.manage');

// ---- NISDAS APPS: documents -------------------------------------------------------------
$router->get('documents', 'DocumentController@index', 'documents.view');
$router->post('documents', 'DocumentController@store', 'documents.manage');
$router->get('documents/{id}/file', 'DocumentController@file', 'documents.view');
$router->post('documents/{id}/archive', 'DocumentController@archive', 'documents.manage');
$router->post('documents/{id}/restore', 'DocumentController@restore', 'documents.manage');
$router->get('documents/pupils/{id}', 'DocumentController@pupil', 'pupils.view');
$router->post('documents/pupils/{id}', 'DocumentController@storePupil', 'documents.manage');
$router->get('documents/staff', 'DocumentController@staffList', 'documents.staff');
$router->get('documents/staff/{id}', 'DocumentController@staff', 'auth');
$router->post('documents/staff/{id}', 'DocumentController@storeStaff', 'documents.staff');
$router->get('documents/my', 'DocumentController@mine', 'staff');
$router->get('documents/person/{id}/file', 'DocumentController@personFile', 'staff');
$router->post('documents/person/{id}/delete', 'DocumentController@deletePerson', 'staff');
$router->get('documents/expiring', 'DocumentController@expiring', 'documents.manage');

// ---- NISDAS APPS: LMS (learning materials) and live classes (Zoom / Google Meet / Teams links) --
$router->get('lms', 'LmsController@index');
$router->get('lms/create', 'LmsController@create', 'lms.manage');
$router->post('lms', 'LmsController@store', 'lms.manage');
$router->get('lms/{id}', 'LmsController@show');
$router->get('lms/{id}/edit', 'LmsController@edit', 'lms.manage');
$router->post('lms/{id}', 'LmsController@update', 'lms.manage');
$router->post('lms/{id}/delete', 'LmsController@delete', 'lms.manage');
$router->get('lms/{id}/file', 'LmsController@file');
$router->get('live', 'LiveController@index');
$router->get('live/create', 'LiveController@create', 'live.manage');
$router->post('live', 'LiveController@store', 'live.manage');
$router->get('live/{id}', 'LiveController@show');
$router->get('live/{id}/edit', 'LiveController@edit', 'live.manage');
$router->post('live/{id}', 'LiveController@update', 'live.manage');
$router->post('live/{id}/cancel', 'LiveController@cancel', 'live.manage');
$router->get('live/{id}/join', 'LiveController@join');

// ---- NISDAS APPS: CRM (enquiries), marketing campaigns, school shop (E-Commerce) ----------------
$router->get('enquire', 'CrmController@publicForm', null);
$router->post('enquire', 'CrmController@publicStore', null);
$router->get('enquire/thanks', 'CrmController@thanks', null);
$router->get('crm', 'CrmController@index', 'crm.manage');
$router->get('crm/create', 'CrmController@create', 'crm.manage');
$router->post('crm', 'CrmController@store', 'crm.manage');
$router->get('crm/{id}', 'CrmController@show', 'crm.manage');
$router->post('crm/{id}', 'CrmController@update', 'crm.manage');
$router->post('crm/{id}/activity', 'CrmController@activity', 'crm.manage');
$router->post('crm/{id}/link', 'CrmController@sendLink', 'crm.manage');
$router->post('crm/{id}/optout', 'CrmController@optOut', 'crm.manage');
$router->get('marketing', 'CrmController@campaigns', 'marketing.send');
$router->get('marketing/create', 'CrmController@newCampaign', 'marketing.send');
$router->post('marketing', 'CrmController@saveCampaign', 'marketing.send');
$router->get('marketing/optouts', 'CrmController@optouts', 'marketing.send');
$router->post('marketing/optouts', 'CrmController@optouts', 'marketing.send');
$router->get('marketing/{id}', 'CrmController@campaign', 'marketing.send');
$router->post('marketing/{id}/send', 'CrmController@sendCampaign', 'marketing.send');
$router->post('marketing/{id}/delete', 'CrmController@deleteCampaign', 'marketing.send');
$router->get('shop', 'ShopController@index', 'shop.order');
$router->get('shop/cart', 'ShopController@cart', 'shop.order');
$router->post('shop/cart', 'ShopController@updateCart', 'shop.order');
$router->post('shop/checkout', 'ShopController@checkout', 'shop.order');
$router->get('shop/orders', 'ShopController@orders', 'shop.order');
$router->post('shop/orders/{id}/cancel', 'ShopController@parentCancel', 'shop.order');
$router->get('shop/admin', 'ShopController@admin', 'shop.manage');
$router->get('shop/admin/orders/{id}', 'ShopController@order', 'shop.manage');
$router->post('shop/admin/orders/{id}', 'ShopController@orderAction', 'shop.manage');
$router->get('shop/admin/products', 'ShopController@products', 'shop.manage');
$router->post('shop/admin/products', 'ShopController@saveProduct', 'shop.manage');
$router->post('shop/admin/products/{id}/stock', 'ShopController@stock', 'shop.manage');
$router->get('shop/admin/sell', 'ShopController@sellForm', 'shop.manage');
$router->post('shop/admin/sell', 'ShopController@sell', 'shop.manage');
$router->get('shop/admin/report', 'ShopController@report', 'shop.manage');

// ---- Governance & risk (Board view) ----------------------------------------------------------------------
$router->get('governance', 'GovernanceController@index', 'governance.view');
$router->get('governance/members', 'GovernanceController@members', 'governance.view');
$router->post('governance/members', 'GovernanceController@saveMember', 'governance.manage');
$router->post('governance/members/{id}', 'GovernanceController@saveMember', 'governance.manage');
$router->get('governance/meetings', 'GovernanceController@meetings', 'governance.view');
$router->post('governance/meetings', 'GovernanceController@saveMeeting', 'governance.manage');
$router->get('governance/meetings/{id}', 'GovernanceController@meeting', 'governance.view');
$router->post('governance/meetings/{id}', 'GovernanceController@saveMeeting', 'governance.manage');
$router->post('governance/meetings/{id}/attendance', 'GovernanceController@attendance', 'governance.manage');
$router->post('governance/meetings/{id}/delete', 'GovernanceController@deleteMeeting', 'governance.manage');
$router->get('governance/policies', 'GovernanceController@policies', 'governance.view');
$router->post('governance/policies', 'GovernanceController@savePolicy', 'governance.manage');
$router->post('governance/policies/{id}', 'GovernanceController@savePolicy', 'governance.manage');
$router->post('governance/policies/{id}/reviewed', 'GovernanceController@policyReviewed', 'governance.manage');
$router->get('governance/risks', 'GovernanceController@risks', 'governance.view');
$router->get('governance/risks/new', 'GovernanceController@newRisk', 'governance.manage');
$router->post('governance/risks', 'GovernanceController@saveRisk', 'governance.manage');
$router->get('governance/risks/{id}', 'GovernanceController@risk', 'governance.view');
$router->post('governance/risks/{id}', 'GovernanceController@saveRisk', 'governance.manage');

// ---- Pupil welfare: behaviour log (positive discipline) and welfare cases ---------------------------------
$router->get('welfare', 'WelfareController@index', 'welfare.record');
$router->get('welfare/behaviour', 'WelfareController@behaviour', 'welfare.record');
$router->get('welfare/behaviour/new', 'WelfareController@newBehaviour', 'welfare.record');
$router->post('welfare/behaviour', 'WelfareController@storeBehaviour', 'welfare.record');
$router->get('welfare/cases', 'WelfareController@cases', 'welfare.record');
$router->get('welfare/cases/new', 'WelfareController@newCase', 'welfare.record');
$router->post('welfare/cases', 'WelfareController@storeCase', 'welfare.record');
$router->get('welfare/cases/{id}', 'WelfareController@show', 'welfare.record');
$router->post('welfare/cases/{id}', 'WelfareController@update', 'welfare.record');
$router->post('welfare/cases/{id}/note', 'WelfareController@note', 'welfare.record');

// ---- GES school records (migration 039) ----------------------------------------------------------------
$router->get('logbook', 'RegisterController@logbook', 'logbook.manage');
$router->post('logbook', 'RegisterController@saveLog', 'logbook.manage');
$router->get('visitors', 'RegisterController@visitors', 'visitors.record');
$router->post('visitors', 'RegisterController@signInVisitor', 'visitors.record');
$router->post('visitors/{id}/out', 'RegisterController@signOutVisitor', 'visitors.record');
$router->get('movements', 'RegisterController@movements', 'staff');
$router->post('movements', 'RegisterController@goOut', 'staff');
$router->post('movements/{id}/back', 'RegisterController@comeBack', 'staff');
$router->get('staff-leave', 'StaffLeaveController@index', 'staff');
$router->post('staff-leave', 'StaffLeaveController@store', 'staff');
$router->post('staff-leave/{id}/decide', 'StaffLeaveController@decide', 'staffleave.approve');
$router->post('staff-leave/{id}/cancel', 'StaffLeaveController@cancel', 'staff');
$router->get('staff-leave/{id}/file', 'StaffLeaveController@file', 'staff');
$router->get('cpd', 'StaffLeaveController@cpd', 'staff');
$router->post('cpd', 'StaffLeaveController@storeCpd', 'staff');
$router->post('cpd/{id}/delete', 'StaffLeaveController@deleteCpd', 'staff');
$router->get('cpd/{id}/file', 'StaffLeaveController@cpdFile', 'staff');
$router->get('stores', 'StoreController@index', 'stores.view');
$router->post('stores/items', 'StoreController@saveItem', 'stores.manage');
$router->get('stores/items/{id}', 'StoreController@item', 'stores.view');
$router->post('stores/moves', 'StoreController@move', 'stores.manage');
$router->get('permits', 'PermitController@index', 'permits.view');
$router->post('permits', 'PermitController@save', 'permits.manage');
$router->post('permits/{id}/delete', 'PermitController@delete', 'permits.manage');
$router->get('permits/{id}/file', 'PermitController@file', 'permits.view');
$router->get('setup/privacy/requests', 'PrivacyController@requests', 'app.configure');
$router->post('setup/privacy/requests', 'PrivacyController@saveRequest', 'app.configure');
$router->post('setup/privacy/request-days', 'PrivacyController@saveRequestDays', 'app.configure');
$router->get('pupils/{id}/record-card', 'PupilController@recordCard', 'pupils.view');

// ---- Gate pick-up check (Security), the parent's SMS answer page and the parent portal page ----------------------
$router->get('gate', 'PickupController@gate', 'pickup.gate');
$router->post('gate', 'PickupController@record', 'pickup.gate');
$router->get('gate/register', 'PickupController@register', 'pickup.manage');
$router->post('gate/people', 'PickupController@officeAddPerson', 'pickup.manage');
$router->get('gate/person/{id}/photo', 'PickupController@personPhoto', 'auth');
$router->get('gate/pupil/{id}/photo', 'PickupController@pupilPhoto', 'pickup.gate');
$router->get('gate/{id}', 'PickupController@show', 'auth');
$router->get('gate/{id}/status', 'PickupController@status', 'auth');
$router->get('gate/{id}/photo', 'PickupController@photo', 'auth');
$router->get('gate/{id}/signature', 'PickupController@signature', 'auth');
$router->post('gate/{id}/release', 'PickupController@release', 'pickup.gate');
$router->post('gate/{id}/resend', 'PickupController@resend', 'pickup.gate');
$router->post('gate/{id}/cancel', 'PickupController@cancel', 'pickup.gate');
$router->post('gate/{id}/decide', 'PickupController@officeDecide', 'pickup.manage');
$router->get('p/{token}', 'PickupController@answer', null);
$router->post('p/{token}', 'PickupController@answerSave', null);
$router->get('p/{token}/photo', 'PickupController@answerPhoto', null);
$router->get('pickup', 'PickupController@mine', 'portal.parent');
$router->post('pickup/people', 'PickupController@mineAddPerson', 'portal.parent');
$router->post('pickup/people/{id}/remove', 'PickupController@mineRemovePerson', 'portal.parent');
$router->post('pickup/codes', 'PickupController@mineCode', 'portal.parent');
$router->post('pickup/{id}/decide', 'PickupController@mineDecide', 'portal.parent');
