<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome | Colegio de Laurel</title>
    <link rel="icon" type="image/png" href="assets/img/CDL_seal.png?v=1">
    <link rel="apple-touch-icon" href="assets/img/CDL_seal.png">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-green: #2d5a27;
            --accent-green: #8bc34a;
            --light-bg: #f4f7f6;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            color: #333;
            background-color: var(--light-bg);
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(rgba(45, 90, 39, 0.85), rgba(45, 90, 39, 0.85)), url('assets/img/School-bg.jfif');
            background-size: cover;
            background-position: center;
            height: 60vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: white;
            padding: 20px;
        }

        .hero img {
            width: 120px;
            margin-bottom: 20px;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.3));
        }

        /* Container Adjustment */
        .main-content {
            padding-bottom: 60px;
        }

        /* Statements Section - MOVED LOWER */
        .statement-card {
            max-width: 900px;
            margin: 40px auto;
            /* Changed from -50px to 40px to move it lower */
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
            position: relative;
        }

        .vision-text {
            font-size: 1.2rem;
            line-height: 1.8;
            color: #444;
            font-style: italic;
            padding: 0 20px;
        }

        .mission-list {
            text-align: left;
            display: inline-block;
            margin-top: 20px;
            padding: 0;
        }

        .mission-list li {
            margin-bottom: 12px;
            list-style: none;
            display: flex;
            align-items: flex-start;
            font-size: 1.05rem;
        }

        .mission-list li i {
            color: var(--primary-green);
            margin-right: 15px;
            margin-top: 5px;
        }

        /* Portal Section */
        .portal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .portal-card {
            background: white;
            padding: 35px;
            border-radius: 12px;
            border-top: 5px solid var(--primary-green);
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .portal-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .portal-card i {
            font-size: 3rem;
            color: var(--primary-green);
            margin-bottom: 20px;
        }

        .portal-card h3 {
            margin-bottom: 10px;
            color: var(--primary-green);
        }

        .portal-card p {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .login-btn-hero {
            background: white;
            color: var(--primary-green);
            padding: 14px 45px;
            text-decoration: none;
            border-radius: 30px;
            font-weight: bold;
            display: inline-block;
            transition: 0.3s;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .login-btn-hero:hover {
            background: var(--accent-green);
            color: white;
            transform: scale(1.05);
        }
    </style>
</head>

<body>

    <section class="hero">
        <img src="assets/img/CDL_seal.png" alt="CDL Logo">
        <h1 style="font-size: 2.8rem; margin: 0; letter-spacing: 1px;">COLEGIO DE LAUREL</h1>
        <p style="font-size: 1.3rem; opacity: 0.95; margin-top: 10px;">Student Information & Enrollment System</p>
        <div style="margin-top: 35px;">
            <a href="login.php" class="login-btn-hero">
                <i class="fas fa-sign-in-alt"></i> ACCESS PORTAL
            </a>
        </div>
    </section>

    <div class="main-content">
        <div class="container">
            <div class="statement-card">
                <h2 style="color: var(--primary-green); text-transform: uppercase; letter-spacing: 2px; margin-bottom: 20px;">Vision Statement</h2>
                <p class="vision-text">
                    "Colegio de Laurel shall be a center of nation builders that will produce competent, God-loving, and transformative leaders and movers of the society and empowered communities."
                </p>

                <hr style="width: 60px; border: 2px solid var(--accent-green); margin: 40px auto;">

                <h2 style="color: var(--primary-green); text-transform: uppercase; letter-spacing: 2px; margin-bottom: 20px;">Mission Statement</h2>
                <ul class="mission-list">
                    <li><i class="fas fa-check-circle"></i> To provide holistic quality higher education through excellent and innovative instructions.</li>
                    <li><i class="fas fa-check-circle"></i> To develop knowledge that will inspire growth and development of the community.</li>
                    <li><i class="fas fa-check-circle"></i> To strengthen core values towards equipped human and social development.</li>
                    <li><i class="fas fa-check-circle"></i> To engage in active community service and remain open and susceptible to change.</li>
                </ul>
            </div>

            <h2 style="text-align: center; color: var(--primary-green); margin-top: 60px; font-weight: 300; text-transform: uppercase; letter-spacing: 1px;">Select your Portal</h2>

            <div class="portal-grid">
                <a href="login.php" class="portal-card">
                    <i class="fas fa-user-graduate"></i>
                    <h3>Student Portal</h3>
                    <p>View your academic prospectus, check grades, and monitor your enrollment status.</p>
                </a>

                <a href="login.php" class="portal-card">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <h3>Faculty Portal</h3>
                    <p>Access your class schedules, manage class lists, and encode student final grades.</p>
                </a>

                <a href="login.php" class="portal-card">
                    <i class="fas fa-user-shield"></i>
                    <h3>Administrator</h3>
                    <p>Manage curriculum settings, monitor user accounts, and generate institutional reports.</p>
                </a>
            </div>
        </div>
    </div>

    <footer style="background: #1a1a1a; color: #999; padding: 50px 20px; text-align: center;">
        <p style="margin-bottom: 10px;">&copy; 2024 Colegio de Laurel. All rights reserved.</p>
        <p style="font-size: 0.85rem; letter-spacing: 0.5px;">Developed for the CDL Registrar's Office</p>
    </footer>

</body>

</html>