<?php
session_start();
if (empty($_SESSION['alogin'])) { header('Location: ../admin-login.php'); exit; }
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Frontend-only Student Result Management System built with HTML, CSS and JavaScript.">
  <title>Student Result Management System</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar" id="sidebar">
      <div class="brand">
        <div class="brand-mark">SR</div>
        <div><strong>ResultHub</strong><span>Student Management</span></div>
      </div>
      <nav class="nav">
        <button class="nav-link active" data-view="dashboard"><span>▦</span> Dashboard</button>
        <button class="nav-link" data-view="students"><span>♙</span> Students</button>
        <button class="nav-link" data-view="results"><span>▤</span> Results</button>
      </nav>
      <div class="sidebar-bottom">
        <div class="demo-note"><strong>PHP + MySQL connected</strong><p>Records are stored in your SRMS database.</p></div>
        <a class="nav-link" href="../dashboard.php"><span>↗</span> Legacy admin tools</a>
        <button class="nav-link" id="exportBtn"><span>⇩</span> Export data (JSON)</button>
      </div>
    </aside>

    <main class="main">
      <header class="topbar">
        <button class="mobile-menu" id="mobileMenu" aria-label="Toggle navigation">☰</button>
        <div><p class="eyebrow">STUDENT ACADEMIC PORTAL</p><h1 id="pageTitle">Dashboard</h1></div>
        <div class="topbar-right"><span class="status-dot"></span><span>Connected to MySQL</span><a class="logout-link" href="../logout.php">Log out ↗</a><div class="avatar">SR</div></div>
      </header>

      <section class="view active" id="dashboardView">
        <div class="welcome">
          <div><p class="eyebrow light">OVERVIEW</p><h2>Welcome to ResultHub</h2><p>Manage student records and view academic performance in one place.</p></div>
          <button class="btn btn-light" data-action="add-student">＋ Add student</button>
        </div>
        <div class="stats-grid">
          <article class="stat-card"><div class="stat-icon purple">♙</div><div><p>Total students</p><h3 id="totalStudents">0</h3><span class="stat-caption">Registered records</span></div></article>
          <article class="stat-card"><div class="stat-icon blue">▤</div><div><p>Results entered</p><h3 id="totalResults">0</h3><span class="stat-caption">Students with marks</span></div></article>
          <article class="stat-card"><div class="stat-icon green">✓</div><div><p>Pass rate</p><h3 id="passRate">—</h3><span class="stat-caption">Based on entered results</span></div></article>
          <article class="stat-card"><div class="stat-icon orange">％</div><div><p>Average percentage</p><h3 id="averagePercent">—</h3><span class="stat-caption">Across available results</span></div></article>
        </div>
        <div class="content-card">
          <div class="section-heading"><div><h2>Recent students</h2><p>Recently added student records</p></div><button class="btn btn-outline" data-view="students">View all students →</button></div>
          <div class="table-wrap"><table><thead><tr><th>Student</th><th>Roll number</th><th>Class</th><th>Result</th><th></th></tr></thead><tbody id="recentStudents"></tbody></table></div>
          <div class="empty-state" id="dashboardEmpty"><div class="empty-icon">♙</div><h3>No students yet</h3><p>Add a student to begin managing academic records.</p><button class="btn btn-primary" data-action="add-student">Add your first student</button></div>
        </div>
      </section>

      <section class="view" id="studentsView">
        <div class="page-tools"><div><h2>Student records</h2><p>Add, search, edit or remove student details.</p></div><button class="btn btn-primary" data-action="add-student">＋ Add student</button></div>
        <div class="content-card">
          <div class="toolbar"><label class="search-box"><span>⌕</span><input id="studentSearch" type="search" placeholder="Search name or roll number..."></label><span class="result-count" id="studentCount">0 students</span></div>
          <div class="table-wrap"><table><thead><tr><th>Student</th><th>Roll number</th><th>Class</th><th>Email</th><th>Result status</th><th>Actions</th></tr></thead><tbody id="studentsTable"></tbody></table></div>
          <div class="empty-state" id="studentsEmpty"><div class="empty-icon">⌕</div><h3>No matching students</h3><p>Try another search or add a new student.</p></div>
        </div>
      </section>

      <section class="view" id="resultsView">
        <div class="page-tools"><div><h2>Examination results</h2><p>Enter marks and generate a printable marksheet.</p></div><button class="btn btn-primary" data-action="add-result">＋ Enter marks</button></div>
        <div class="content-card">
          <div class="toolbar"><label class="search-box"><span>⌕</span><input id="resultSearch" type="search" placeholder="Search by student or roll number..."></label><span class="result-count" id="resultCount">0 results</span></div>
          <div class="table-wrap"><table><thead><tr><th>Student</th><th>Roll number</th><th>Total / 500</th><th>Percentage</th><th>Grade</th><th>Status</th><th>Actions</th></tr></thead><tbody id="resultsTable"></tbody></table></div>
          <div class="empty-state" id="resultsEmpty"><div class="empty-icon">▤</div><h3>No results available</h3><p>Add a student first, then enter their subject marks.</p></div>
        </div>
      </section>
    </main>
  </div>

  <div class="modal-backdrop" id="modalBackdrop" hidden>
    <section class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
      <div class="modal-header"><div><p class="eyebrow">RECORD MANAGEMENT</p><h2 id="modalTitle">Add student</h2></div><button class="icon-btn" id="closeModal" aria-label="Close">×</button></div>
      <form id="studentForm">
        <input type="hidden" id="editId">
        <div class="form-grid">
          <label>Full name <input id="studentName" required maxlength="80" placeholder="e.g. Anuj Kumar"></label>
          <label>Roll number <input id="rollNumber" required maxlength="30" placeholder="e.g. 10861"></label>
          <label>Class / semester <input id="studentClass" required maxlength="40" placeholder="e.g. B.Tech CSE - Semester 4"></label>
          <label>Email (optional) <input id="studentEmail" type="email" maxlength="120" placeholder="student@example.com"></label>
        </div>
        <p class="form-hint">Roll numbers must be unique.</p>
        <div class="modal-actions"><button type="button" class="btn btn-outline" id="cancelModal">Cancel</button><button type="submit" class="btn btn-primary">Save student</button></div>
      </form>
    </section>
  </div>

  <div class="modal-backdrop" id="marksBackdrop" hidden>
    <section class="modal" role="dialog" aria-modal="true" aria-labelledby="marksTitle">
      <div class="modal-header"><div><p class="eyebrow">ACADEMIC RECORD</p><h2 id="marksTitle">Enter examination marks</h2></div><button class="icon-btn" id="closeMarks" aria-label="Close">×</button></div>
      <form id="marksForm">
        <label>Student <select id="marksStudent" required></select></label>
        <div class="form-grid marks-grid" id="dynamicMarks"></div>
        <p class="form-hint">Subjects come from the selected class in your MySQL database. Pass requires at least 33 marks in every subject.</p>
        <div class="modal-actions"><button type="button" class="btn btn-outline" id="cancelMarks">Cancel</button><button type="submit" class="btn btn-primary">Save result</button></div>
      </form>
    </section>
  </div>

  <div class="toast" id="toast" role="status" aria-live="polite"></div>
  <script>window.SRMS_CSRF = <?php echo json_encode($_SESSION['csrf_token']); ?>;</script>
  <script src="js/app.js"></script>
</body>
</html>