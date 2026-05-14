"""
report_generator.py
Generates PDF reports for local_codejudge plugin.
Called by the Flask judge service via /report/teacher and /report/student endpoints.
"""

import io
import math
from datetime import datetime

from reportlab.lib.pagesizes import A4
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import mm, cm
from reportlab.lib.enums import TA_CENTER, TA_LEFT, TA_RIGHT
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle,
    HRFlowable, PageBreak, KeepTogether
)
from reportlab.graphics.shapes import Drawing, Rect, String, Line, Wedge, Circle
from reportlab.graphics.charts.barcharts import VerticalBarChart
from reportlab.graphics import renderPDF

# ── Colour palette ────────────────────────────────────────────────────────────
NAVY      = colors.HexColor('#1B3A6B')
BLUE      = colors.HexColor('#2E86AB')
TEAL      = colors.HexColor('#17A589')
GREEN     = colors.HexColor('#1E8449')
RED       = colors.HexColor('#C0392B')
ORANGE    = colors.HexColor('#D68910')
LIGHT_BG  = colors.HexColor('#EAF2FB')
LIGHT_GR  = colors.HexColor('#E8F8F5')
LIGHT_RED = colors.HexColor('#FDECEA')
GREY_BG   = colors.HexColor('#F4F6F7')
GREY_TEXT = colors.HexColor('#7F8C8D')
WHITE     = colors.white
BLACK     = colors.HexColor('#2C3E50')
GOLD      = colors.HexColor('#F39C12')
SILVER    = colors.HexColor('#95A5A6')
BRONZE    = colors.HexColor('#CA6F1E')

W, H = A4

# ── Styles ────────────────────────────────────────────────────────────────────
def make_styles():
    s = getSampleStyleSheet()
    def add(name, **kw):
        s.add(ParagraphStyle(name=name, **kw))

    add('CJTitle',      fontName='Helvetica-Bold', fontSize=26, textColor=NAVY,
        alignment=TA_CENTER, spaceAfter=15)
    add('CJSubtitle',   fontName='Helvetica', fontSize=13, textColor=BLUE,
        alignment=TA_CENTER, spaceAfter=2)
    add('CJMeta',       fontName='Helvetica', fontSize=9, textColor=GREY_TEXT,
        alignment=TA_CENTER, spaceAfter=16)
    add('CJSection',    fontName='Helvetica-Bold', fontSize=14, textColor=NAVY,
        spaceBefore=18, spaceAfter=8)
    add('CJSubSection', fontName='Helvetica-Bold', fontSize=11, textColor=BLUE,
        spaceBefore=10, spaceAfter=6)
    add('CJBody',       fontName='Helvetica', fontSize=9, textColor=BLACK,
        spaceAfter=4, leading=14)
    add('CJBodyC',      fontName='Helvetica', fontSize=9, textColor=BLACK,
        alignment=TA_CENTER, spaceAfter=4)
    add('CJSmall',      fontName='Helvetica', fontSize=8, textColor=GREY_TEXT,
        spaceAfter=2)
    add('CJCode',       fontName='Courier', fontSize=8, textColor=BLACK,
        backColor=GREY_BG, spaceAfter=4, leftIndent=8, rightIndent=8,
        spaceBefore=4, leading=12)
    add('CJHighlight',  fontName='Helvetica-Bold', fontSize=10, textColor=NAVY,
        alignment=TA_CENTER)
    add('CJLabel',      fontName='Helvetica-Bold', fontSize=8, textColor=GREY_TEXT,
        alignment=TA_CENTER)
    return s

# ── Helpers ───────────────────────────────────────────────────────────────────
def hr(color=BLUE, thickness=1):
    return HRFlowable(width='100%', thickness=thickness, color=color,
                      spaceAfter=6, spaceBefore=6)

def spacer(h=6):
    return Spacer(1, h)

def section_heading(text, styles):
    return KeepTogether([
        Paragraph(text, styles['CJSection']),
        HRFlowable(width='100%', thickness=2, color=BLUE, spaceAfter=8)
    ])

def tbl_style(header_color=NAVY, stripe=GREY_BG):
    return TableStyle([
        ('BACKGROUND',   (0,0), (-1,0),  header_color),
        ('TEXTCOLOR',    (0,0), (-1,0),  WHITE),
        ('FONTNAME',     (0,0), (-1,0),  'Helvetica-Bold'),
        ('FONTSIZE',     (0,0), (-1,0),  9),
        ('ALIGN',        (0,0), (-1,0),  'CENTER'),
        ('TOPPADDING',   (0,0), (-1,0),  7),
        ('BOTTOMPADDING',(0,0), (-1,0),  7),
        ('FONTNAME',     (0,1), (-1,-1), 'Helvetica'),
        ('FONTSIZE',     (0,1), (-1,-1), 8),
        ('ALIGN',        (0,1), (-1,-1), 'CENTER'),
        ('VALIGN',       (0,0), (-1,-1), 'MIDDLE'),
        ('TOPPADDING',   (0,1), (-1,-1), 5),
        ('BOTTOMPADDING',(0,1), (-1,-1), 5),
        ('ROWBACKGROUNDS',(0,1),(-1,-1), [WHITE, stripe]),
        ('GRID',         (0,0), (-1,-1), 0.5, colors.HexColor('#BDC3C7')),
        ('ROUNDEDCORNERS',[2]),
    ])

def medal_color(rank):
    if rank == 1: return GOLD
    if rank == 2: return SILVER
    if rank == 3: return BRONZE
    return BLACK

def pct(val, total):
    if total == 0: return 0.0
    return round((val / total) * 100, 1)

def grade(score_pct):
    if score_pct >= 90: return 'A+'
    if score_pct >= 80: return 'A'
    if score_pct >= 70: return 'B'
    if score_pct >= 60: return 'C'
    if score_pct >= 50: return 'D'
    return 'F'

def grade_color(score_pct):
    if score_pct >= 70: return GREEN
    if score_pct >= 50: return ORANGE
    return RED

# ── Simple bar chart drawing ──────────────────────────────────────────────────
def bar_chart(labels, values, width=400, height=140,
              bar_color=BLUE, title=''):
    """Return a Drawing with a basic bar chart."""
    d = Drawing(width, height + 30)
    n = len(labels)
    if n == 0:
        return d

    max_val = max(values) if values else 1
    if max_val == 0: max_val = 1

    margin_l, margin_r, margin_b, margin_t = 30, 10, 30, 20
    chart_w = width - margin_l - margin_r
    chart_h = height - margin_b - margin_t
    bar_w   = min(chart_w / n * 0.6, 40)
    gap     = chart_w / n

    # Y axis
    d.add(Line(margin_l, margin_b, margin_l, margin_b + chart_h,
               strokeColor=GREY_TEXT, strokeWidth=0.5))
    # X axis
    d.add(Line(margin_l, margin_b, margin_l + chart_w, margin_b,
               strokeColor=GREY_TEXT, strokeWidth=0.5))

    # Y gridlines + labels
    for i in range(5):
        y_val = max_val * i / 4
        y_px  = margin_b + (y_val / max_val) * chart_h
        d.add(Line(margin_l, y_px, margin_l + chart_w, y_px,
                   strokeColor=colors.HexColor('#E0E0E0'), strokeWidth=0.3))
        d.add(String(margin_l - 3, y_px - 3, str(int(y_val)),
                     fontSize=6, textAnchor='end', fillColor=GREY_TEXT))

    # Bars
    for i, (label, val) in enumerate(zip(labels, values)):
        x   = margin_l + i * gap + gap / 2 - bar_w / 2
        bar_h = (val / max_val) * chart_h if max_val else 0
        y   = margin_b

        d.add(Rect(x, y, bar_w, bar_h,
                   fillColor=bar_color, strokeColor=None, rx=2, ry=2))

        # Value on top
        if bar_h > 10:
            d.add(String(x + bar_w / 2, y + bar_h + 2, str(int(val)),
                         fontSize=7, textAnchor='middle', fillColor=NAVY))

        # Label below
        short = label[:8] + '..' if len(label) > 10 else label
        d.add(String(x + bar_w / 2, margin_b - 14, short,
                     fontSize=6, textAnchor='middle', fillColor=BLACK))

    # Title
    if title:
        d.add(String(width / 2, margin_b + chart_h + margin_t + 2, title,
                     fontSize=8, textAnchor='middle', fillColor=NAVY))
    return d

# ── Donut / pie chart ─────────────────────────────────────────────────────────
def donut(pass_count, fail_count, size=90):
    """Draw a simple pass/fail donut using rectangles (no arc division-by-zero)."""
    d = Drawing(size, size + 20)
    total = pass_count + fail_count
    pct_val = int(pass_count / total * 100) if total > 0 else 0

    # Background circle via rect approximation - use two colored bars
    bar_w = size - 10
    bar_h = 14
    y0 = size / 2 - bar_h / 2

    # Background
    d.add(Rect(5, y0 - bar_h, bar_w, bar_h * 2,
               fillColor=GREY_BG, strokeColor=None, rx=6, ry=6))

    # Pass portion
    pass_w = int(bar_w * pct_val / 100) if total > 0 else 0
    if pass_w > 0:
        d.add(Rect(5, y0 - bar_h, pass_w, bar_h * 2,
                   fillColor=GREEN, strokeColor=None, rx=6, ry=6))

    # Text
    clr = GREEN if pct_val >= 60 else RED
    d.add(String(size/2, size/2 - 5, f'{pct_val}% PASS',
                 fontSize=10, textAnchor='middle',
                 fillColor=WHITE, fontName='Helvetica-Bold'))
    d.add(String(size/2, y0 - bar_h - 12, f'{pass_count}P / {fail_count}F',
                 fontSize=8, textAnchor='middle', fillColor=GREY_TEXT))
    return d

# ── Metric box (stat card) ────────────────────────────────────────────────────
def hex_str(clr):
    r = int(clr.red * 255)
    g = int(clr.green * 255)
    b = int(clr.blue * 255)
    return f'#{r:02X}{g:02X}{b:02X}'

def stat_table(stats, styles):
    """stats = list of (label, value, color)"""
    cells = []
    for label, value, clr in stats:
        cells.append([
            Paragraph(f'<font color="{hex_str(clr)}"><b>{value}</b></font>',
                      styles['CJHighlight']),
            Paragraph(label, styles['CJLabel'])
        ])

    rows = []
    row = []
    for i, cell in enumerate(cells):
        row.append(cell)
        if (i + 1) % 4 == 0 or i == len(cells) - 1:
            while len(row) < 4:
                row.append(['', ''])
            rows.append(row)
            row = []

    col_w = (W - 60) / 4
    tbl_data = []
    for row in rows:
        tbl_data.append([c[0] for c in row])
        tbl_data.append([c[1] for c in row])

    t = Table(tbl_data, colWidths=[col_w]*4)
    t.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,-1), GREY_BG),
        ('BOX',        (0,0), (-1,-1), 0.5, colors.HexColor('#BDC3C7')),
        ('INNERGRID',  (0,0), (-1,-1), 0.5, WHITE),
        ('ALIGN',      (0,0), (-1,-1), 'CENTER'),
        ('VALIGN',     (0,0), (-1,-1), 'MIDDLE'),
        ('TOPPADDING', (0,0), (-1,-1), 6),
        ('BOTTOMPADDING',(0,0),(-1,-1),4),
        ('ROUNDEDCORNERS',[4]),
    ]))
    return t

# ══════════════════════════════════════════════════════════════════════════════
# TEACHER REPORT
# ══════════════════════════════════════════════════════════════════════════════
def generate_teacher_report(data: dict) -> bytes:
    """
    data keys:
      site_name, generated_at,
      students: [{id, name, submissions:[{question, language, marks, max_marks,
                   status, timecreated, reports:[{input,expected,actual,result}]}]}]
      questions: [{id, title, marks, total_submissions, pass_count, avg_score}]
    """
    buf    = io.BytesIO()
    styles = make_styles()
    story  = []

    students  = data.get('students', [])
    questions = data.get('questions', [])
    site      = data.get('site_name', 'Moodle')
    gen_at    = data.get('generated_at', datetime.now().strftime('%d %B %Y, %I:%M %p'))

    # ── Cover ──────────────────────────────────────────────────────────────────
    story += [
        spacer(40),
        Paragraph('CodeJudge', styles['CJTitle']),
        Paragraph('Class Performance Report', styles['CJSubtitle']),
        Paragraph(f'{site}  |  Generated: {gen_at}', styles['CJMeta']),
        hr(NAVY, 2),
        spacer(10),
    ]

    # ── Overall class metrics ──────────────────────────────────────────────────
    total_students    = len(students)
    all_submissions   = [s for st in students for s in st.get('submissions', [])]
    total_subs        = len(all_submissions)
    completed_subs    = [s for s in all_submissions if s.get('status') == 'completed']
    total_marks_earned= sum(float(s.get('marks', 0)) for s in completed_subs)
    total_marks_avail = sum(float(s.get('max_marks', 0)) for s in completed_subs)
    class_avg_pct     = pct(total_marks_earned, total_marks_avail) if total_marks_avail else 0

    story.append(section_heading('1.  Class Overview', styles))

    story.append(stat_table([
        ('Total Students',    str(total_students),            NAVY),
        ('Total Submissions', str(total_subs),                BLUE),
        ('Questions Set',     str(len(questions)),            TEAL),
        ('Class Avg Score',   f'{class_avg_pct}%',           GREEN if class_avg_pct>=60 else RED),
        ('Completed Runs',    str(len(completed_subs)),       BLUE),
        ('Overall Grade',     grade(class_avg_pct),          NAVY),
        ('Marks Earned',      f'{total_marks_earned:.0f}',   TEAL),
        ('Marks Available',   f'{total_marks_avail:.0f}',    GREY_TEXT),
    ], styles))
    story.append(spacer(10))

    # Pass/fail donut + language distribution
    all_reports = [r for s in all_submissions for r in s.get('reports', [])]
    pass_count  = sum(1 for r in all_reports if r.get('result') == 'PASS')
    fail_count  = len(all_reports) - pass_count

    lang_counts = {}
    for s in all_submissions:
        l = s.get('language', 'unknown')
        lang_counts[l] = lang_counts.get(l, 0) + 1

    donut_d = donut(pass_count, fail_count, size=100)
    lang_d  = bar_chart(
        list(lang_counts.keys()),
        list(lang_counts.values()),
        width=260, height=100, bar_color=TEAL,
        title='Submissions by Language'
    )

    tbl = Table(
        [[donut_d, lang_d]],
        colWidths=[120, 280]
    )
    tbl.setStyle(TableStyle([
        ('ALIGN',  (0,0), (-1,-1), 'CENTER'),
        ('VALIGN', (0,0), (-1,-1), 'MIDDLE'),
        ('BACKGROUND', (0,0), (-1,-1), GREY_BG),
        ('BOX', (0,0), (-1,-1), 0.5, colors.HexColor('#BDC3C7')),
        ('TOPPADDING', (0,0), (-1,-1), 10),
        ('BOTTOMPADDING', (0,0), (-1,-1), 10),
    ]))

    legend = Table(
        [[Paragraph(f'<font color="#1E8449">&#9632;</font> PASS: {pass_count}', styles['CJBody']),
          Paragraph(f'<font color="#C0392B">&#9632;</font> FAIL: {fail_count}', styles['CJBody'])]],
        colWidths=[60, 60]
    )

    story += [tbl, spacer(4), legend, spacer(16)]

    # ── Question analysis ─────────────────────────────────────────────────────
    story.append(section_heading('2.  Question Analysis', styles))

    q_names  = [q.get('title', '')[:20] for q in questions]
    q_subs   = [int(q.get('total_submissions', 0)) for q in questions]
    q_avg    = [float(q.get('avg_score', 0)) for q in questions]

    if q_names:
        sub_chart = bar_chart(q_names, q_subs,  width=250, height=120,
                              bar_color=BLUE,  title='Submissions per Question')
        avg_chart = bar_chart(q_names, q_avg,   width=250, height=120,
                              bar_color=GREEN, title='Avg Score per Question')
        charts_tbl = Table([[sub_chart, avg_chart]], colWidths=[260, 260])
        charts_tbl.setStyle(TableStyle([
            ('ALIGN',  (0,0),(-1,-1),'CENTER'),
            ('VALIGN', (0,0),(-1,-1),'MIDDLE'),
        ]))
        story += [charts_tbl, spacer(10)]

    if questions:
        sorted_q = sorted(questions, key=lambda x: float(x.get('avg_score',0)), reverse=True)
        most_solved  = sorted_q[0]  if sorted_q else None
        least_solved = sorted_q[-1] if sorted_q else None

        q_table_data = [['#', 'Question Title', 'Max Marks', 'Submissions',
                          'Avg Score', 'Pass Rate', 'Difficulty']]
        for i, q in enumerate(questions, 1):
            avg  = float(q.get('avg_score', 0))
            mx   = float(q.get('marks', 1))
            pr   = pct(avg, mx)
            diff = 'Easy' if pr >= 70 else ('Medium' if pr >= 40 else 'Hard')
            q_table_data.append([
                str(i),
                q.get('title', '')[:35],
                str(q.get('marks', 0)),
                str(q.get('total_submissions', 0)),
                f"{avg:.1f}",
                f"{pr}%",
                diff
            ])

        q_tbl = Table(q_table_data, colWidths=[20, 170, 55, 65, 60, 60, 50])
        q_tbl.setStyle(tbl_style())
        # Color difficulty
        for i, q in enumerate(questions, 1):
            avg = float(q.get('avg_score', 0))
            mx  = float(q.get('marks', 1))
            pr  = pct(avg, mx)
            clr = GREEN if pr >= 70 else (ORANGE if pr >= 40 else RED)
            q_tbl.setStyle(TableStyle([('TEXTCOLOR', (6,i),(6,i), clr),
                                        ('FONTNAME',  (6,i),(6,i), 'Helvetica-Bold')]))

        story += [q_tbl, spacer(8)]

        if most_solved and least_solved:
            highlight = Table(
                [[Paragraph(f'<b>Most Solved:</b> {most_solved.get("title","")}  '
                            f'(Avg {float(most_solved.get("avg_score",0)):.1f} marks)',
                            styles['CJBody']),
                  Paragraph(f'<b>Least Solved:</b> {least_solved.get("title","")}  '
                            f'(Avg {float(least_solved.get("avg_score",0)):.1f} marks)',
                            styles['CJBody'])]],
                colWidths=[(W-60)/2, (W-60)/2]
            )
            highlight.setStyle(TableStyle([
                ('BACKGROUND', (0,0),(0,0), LIGHT_GR),
                ('BACKGROUND', (1,0),(1,0), LIGHT_RED),
                ('BOX', (0,0),(-1,-1), 0.5, colors.HexColor('#BDC3C7')),
                ('INNERGRID', (0,0),(-1,-1), 0.5, WHITE),
                ('TOPPADDING', (0,0),(-1,-1), 8),
                ('BOTTOMPADDING', (0,0),(-1,-1), 8),
                ('LEFTPADDING', (0,0),(-1,-1), 10),
            ]))
            story += [highlight, spacer(16)]

    # ── Student rankings ──────────────────────────────────────────────────────
    story.append(section_heading('3.  Student Rankings', styles))

    student_scores = []
    for st in students:
        subs     = [s for s in st.get('submissions',[]) if s.get('status')=='completed']
        earned   = sum(float(s.get('marks',0)) for s in subs)
        avail    = sum(float(s.get('max_marks',0)) for s in subs)
        sc_pct   = pct(earned, avail) if avail else 0
        student_scores.append({
            'name':    st.get('name','Unknown'),
            'earned':  earned,
            'avail':   avail,
            'pct':     sc_pct,
            'subs':    len(subs),
            'grade':   grade(sc_pct),
        })

    student_scores.sort(key=lambda x: x['pct'], reverse=True)

    if student_scores:
        top3    = student_scores[:3]
        bottom3 = student_scores[-3:][::-1]

        medals = ['🥇 1st', '🥈 2nd', '🥉 3rd']
        top_data = [['Rank', 'Student Name', 'Score', 'Percentage', 'Grade', 'Submissions']]
        for i, s in enumerate(top3):
            top_data.append([
                medals[i] if i < 3 else f'#{i+1}',
                s['name'],
                f"{s['earned']:.0f} / {s['avail']:.0f}",
                f"{s['pct']}%",
                s['grade'],
                str(s['subs'])
            ])
        top_tbl = Table(top_data, colWidths=[60,170,90,70,50,70])
        top_tbl.setStyle(tbl_style(NAVY))
        for i in range(min(3, len(top3))):
            clr = [GOLD, SILVER, BRONZE][i]
            top_tbl.setStyle(TableStyle([
                ('BACKGROUND', (0,i+1),(0,i+1), clr),
                ('TEXTCOLOR',  (0,i+1),(0,i+1), WHITE),
                ('FONTNAME',   (0,i+1),(0,i+1), 'Helvetica-Bold'),
            ]))

        story += [
            Paragraph('<b>Top 3 Students</b>', styles['CJSubSection']),
            top_tbl, spacer(12)
        ]

        bot_data = [['Rank', 'Student Name', 'Score', 'Percentage', 'Grade', 'Note']]
        total_s  = len(student_scores)
        for i, s in enumerate(bottom3):
            rank = total_s - i
            bot_data.append([
                f'#{rank}',
                s['name'],
                f"{s['earned']:.0f} / {s['avail']:.0f}",
                f"{s['pct']}%",
                s['grade'],
                'Needs Support'
            ])
        bot_tbl = Table(bot_data, colWidths=[40,180,90,70,50,80])
        bot_tbl.setStyle(tbl_style(RED))

        story += [
            Paragraph('<b>Bottom 3 Students — Need Attention</b>', styles['CJSubSection']),
            bot_tbl, spacer(12)
        ]

    # Full ranking table
    all_rank_data = [['Rank', 'Student', 'Marks Earned', 'Max Available',
                       'Score %', 'Grade', 'Submissions']]
    for i, s in enumerate(student_scores, 1):
        all_rank_data.append([
            str(i), s['name'],
            f"{s['earned']:.0f}", f"{s['avail']:.0f}",
            f"{s['pct']}%", s['grade'], str(s['subs'])
        ])

    all_tbl = Table(all_rank_data, colWidths=[30,160,80,80,60,50,60])
    all_tbl.setStyle(tbl_style())
    for i, s in enumerate(student_scores, 1):
        clr = GREEN if s['pct'] >= 70 else (ORANGE if s['pct'] >= 50 else RED)
        all_tbl.setStyle(TableStyle([
            ('TEXTCOLOR', (4,i),(5,i), clr),
            ('FONTNAME',  (4,i),(5,i), 'Helvetica-Bold'),
        ]))

    story += [
        Paragraph('<b>Complete Student Rankings</b>', styles['CJSubSection']),
        all_tbl, spacer(16)
    ]

    # ── Grade distribution ────────────────────────────────────────────────────
    story.append(section_heading('4.  Grade Distribution', styles))

    grade_counts = {'A+':0,'A':0,'B':0,'C':0,'D':0,'F':0}
    for s in student_scores:
        g = s['grade']
        grade_counts[g] = grade_counts.get(g, 0) + 1

    grade_chart = bar_chart(
        list(grade_counts.keys()),
        list(grade_counts.values()),
        width=380, height=120,
        bar_color=BLUE, title='Grade Distribution'
    )

    grade_tbl_data = [['Grade', 'Count', 'Percentage', 'Range']]
    ranges = {'A+':'90-100%','A':'80-89%','B':'70-79%','C':'60-69%','D':'50-59%','F':'0-49%'}
    for g, cnt in grade_counts.items():
        grade_tbl_data.append([
            g, str(cnt),
            f"{pct(cnt, total_students)}%",
            ranges.get(g,'')
        ])

    g_tbl = Table(grade_tbl_data, colWidths=[40, 60, 80, 80])
    g_tbl.setStyle(tbl_style())

    story += [
        Table([[grade_chart, g_tbl]], colWidths=[400, 280]),
        spacer(16)
    ]

    # ── Per-student detailed submissions ──────────────────────────────────────
    story.append(PageBreak())
    story.append(section_heading('5.  Detailed Student Submissions', styles))

    for st in students:
        subs = st.get('submissions', [])
        story.append(KeepTogether([
            Paragraph(f'<b>{st.get("name","")}</b>', styles['CJSubSection']),
        ]))

        if not subs:
            story.append(Paragraph('No submissions.', styles['CJSmall']))
            story.append(spacer(8))
            continue

        sub_data = [['Question', 'Language', 'Score', 'Max', '%', 'Grade', 'Date']]
        for s in subs:
            mx  = float(s.get('max_marks', 0))
            sc  = float(s.get('marks', 0))
            p   = pct(sc, mx) if mx else 0
            dt  = datetime.fromtimestamp(int(s.get('timecreated',0))).strftime('%d %b %Y') \
                  if s.get('timecreated') else '—'
            sub_data.append([
                s.get('question','')[:30],
                s.get('language','').upper(),
                f"{sc:.0f}", f"{mx:.0f}", f"{p}%",
                grade(p), dt
            ])

        st_tbl = Table(sub_data, colWidths=[160,55,40,40,45,40,80])
        st_tbl.setStyle(tbl_style(NAVY))
        story += [st_tbl, spacer(10)]

    # ── Footer / build ────────────────────────────────────────────────────────
    def add_page_footer(canvas, doc):
        canvas.saveState()
        canvas.setFont('Helvetica', 7)
        canvas.setFillColor(GREY_TEXT)
        canvas.drawString(30, 20, f'CodeJudge Class Report  |  {site}  |  {gen_at}')
        canvas.drawRightString(W - 30, 20, f'Page {doc.page}')
        canvas.line(30, 28, W - 30, 28)
        canvas.restoreState()

    doc = SimpleDocTemplate(
        buf, pagesize=A4,
        leftMargin=30, rightMargin=30,
        topMargin=30, bottomMargin=30,
        title='CodeJudge Class Report'
    )
    doc.build(story, onFirstPage=add_page_footer, onLaterPages=add_page_footer)
    return buf.getvalue()


# ══════════════════════════════════════════════════════════════════════════════
# STUDENT REPORT
# ══════════════════════════════════════════════════════════════════════════════
def generate_student_report(data: dict) -> bytes:
    """
    data keys:
      student_name, site_name, generated_at,
      submissions: [{question, language, marks, max_marks, status,
                     timecreated, reports:[{input,expected,actual,result}]}]
      class_avg_pct (optional)
      rank, total_students (optional)
    """
    buf    = io.BytesIO()
    styles = make_styles()
    story  = []

    name    = data.get('student_name', 'Student')
    site    = data.get('site_name', 'Moodle')
    gen_at  = data.get('generated_at', datetime.now().strftime('%d %B %Y, %I:%M %p'))
    subs    = data.get('submissions', [])
    done    = [s for s in subs if s.get('status') == 'completed']

    total_earned = sum(float(s.get('marks', 0)) for s in done)
    total_avail  = sum(float(s.get('max_marks', 0)) for s in done)
    score_pct    = pct(total_earned, total_avail) if total_avail else 0
    my_grade     = grade(score_pct)

    all_reports  = [r for s in done for r in s.get('reports', [])]
    pass_count   = sum(1 for r in all_reports if r.get('result') == 'PASS')
    fail_count   = len(all_reports) - pass_count

    # ── Cover ──────────────────────────────────────────────────────────────────
    story += [
        spacer(40),
        Paragraph('CodeJudge', styles['CJTitle']),
        Paragraph('Personal Performance Report', styles['CJSubtitle']),
        Paragraph(f'{name}  |  {site}  |  {gen_at}', styles['CJMeta']),
        hr(NAVY, 2),
        spacer(10),
    ]

    # ── My stats ──────────────────────────────────────────────────────────────
    story.append(section_heading('1.  My Performance Summary', styles))

    rank_str = f'#{data["rank"]} / {data["total_students"]}' \
               if data.get('rank') else 'N/A'

    story.append(stat_table([
        ('Questions Attempted',  str(len(done)),                 NAVY),
        ('Total Marks Earned',   f'{total_earned:.0f}',          BLUE),
        ('Total Marks Available',f'{total_avail:.0f}',           TEAL),
        ('My Score',             f'{score_pct}%',                GREEN if score_pct>=60 else RED),
        ('My Grade',             my_grade,                       NAVY),
        ('Test Cases Passed',    str(pass_count),                GREEN),
        ('Test Cases Failed',    str(fail_count),                RED),
        ('Class Rank',           rank_str,                       GOLD),
    ], styles))
    story.append(spacer(10))

    # Donut + lang breakdown
    lang_counts = {}
    for s in subs:
        l = s.get('language', 'unknown')
        lang_counts[l] = lang_counts.get(l, 0) + 1

    donut_d = donut(pass_count, fail_count, size=100)
    lang_d  = bar_chart(
        list(lang_counts.keys()),
        list(lang_counts.values()),
        width=260, height=100, bar_color=TEAL,
        title='My Submissions by Language'
    )

    charts = Table([[donut_d, lang_d]], colWidths=[120, 280])
    charts.setStyle(TableStyle([
        ('ALIGN',  (0,0),(-1,-1),'CENTER'),
        ('VALIGN', (0,0),(-1,-1),'MIDDLE'),
        ('BACKGROUND',(0,0),(-1,-1),GREY_BG),
        ('BOX',(0,0),(-1,-1),0.5,colors.HexColor('#BDC3C7')),
        ('TOPPADDING',(0,0),(-1,-1),10),
        ('BOTTOMPADDING',(0,0),(-1,-1),10),
    ]))

    class_avg = data.get('class_avg_pct', 0)
    compare_tbl = Table(
        [[Paragraph(f'My Score: <b><font color="#1E8449">{score_pct}%</font></b>', styles['CJBody']),
          Paragraph(f'Class Average: <b>{class_avg}%</b>', styles['CJBody']),
          Paragraph(f'Status: <b>{"Above" if score_pct >= class_avg else "Below"} Average</b>',
                    styles['CJBody'])]],
        colWidths=[(W-60)/3]*3
    )
    compare_tbl.setStyle(TableStyle([
        ('BACKGROUND',(0,0),(-1,-1),LIGHT_BG),
        ('BOX',(0,0),(-1,-1),0.5,colors.HexColor('#BDC3C7')),
        ('ALIGN',(0,0),(-1,-1),'CENTER'),
        ('TOPPADDING',(0,0),(-1,-1),8),
        ('BOTTOMPADDING',(0,0),(-1,-1),8),
    ]))

    story += [charts, spacer(6), compare_tbl, spacer(16)]

    # ── Submission history ────────────────────────────────────────────────────
    story.append(section_heading('2.  Submission History', styles))

    if not done:
        story.append(Paragraph('No completed submissions yet.', styles['CJBody']))
    else:
        # Score progress chart (score per submission over time)
        scores_over_time = [pct(float(s.get('marks',0)), float(s.get('max_marks',1)))
                            for s in done if float(s.get('max_marks',0)) > 0]
        labels_time = [s.get('question','')[:8] for s in done
                       if float(s.get('max_marks',0)) > 0]

        if scores_over_time:
            prog_chart = bar_chart(
                labels_time, scores_over_time,
                width=W - 60, height=120,
                bar_color=BLUE, title='Score % per Question'
            )
            story += [prog_chart, spacer(10)]

        hist_data = [['#', 'Question', 'Language', 'Marks', 'Max', 'Score %', 'Grade', 'Date']]
        for i, s in enumerate(done, 1):
            mx  = float(s.get('max_marks', 0))
            sc  = float(s.get('marks', 0))
            p   = pct(sc, mx) if mx else 0
            dt  = datetime.fromtimestamp(int(s.get('timecreated',0))).strftime('%d %b %Y') \
                  if s.get('timecreated') else '—'
            hist_data.append([
                str(i),
                s.get('question','')[:28],
                s.get('language','').upper(),
                f"{sc:.0f}", f"{mx:.0f}", f"{p}%",
                grade(p), dt
            ])

        h_tbl = Table(hist_data, colWidths=[20,140,55,40,40,50,40,80])
        h_tbl.setStyle(tbl_style())
        for i, s in enumerate(done, 1):
            mx = float(s.get('max_marks', 0))
            sc = float(s.get('marks', 0))
            p  = pct(sc, mx) if mx else 0
            clr = GREEN if p >= 70 else (ORANGE if p >= 50 else RED)
            h_tbl.setStyle(TableStyle([
                ('TEXTCOLOR',(5,i),(6,i), clr),
                ('FONTNAME', (5,i),(6,i), 'Helvetica-Bold'),
            ]))
        story += [h_tbl, spacer(16)]

    # ── Question-by-question detail ───────────────────────────────────────────
    story.append(PageBreak())
    story.append(section_heading('3.  Detailed Results per Question', styles))

    for sub in done:
        rpts = sub.get('reports', [])
        mx   = float(sub.get('max_marks', 0))
        sc   = float(sub.get('marks', 0))
        p    = pct(sc, mx) if mx else 0
        dt   = datetime.fromtimestamp(int(sub.get('timecreated',0))).strftime('%d %b %Y %H:%M') \
               if sub.get('timecreated') else '—'

        header_clr = GREEN if p >= 70 else (ORANGE if p >= 50 else RED)
        story.append(KeepTogether([
            Paragraph(
                f'<font color="{hex_str(header_clr)}">&#9632;</font>  '
                f'<b>{sub.get("question","")}</b>  —  '
                f'{sc:.0f}/{mx:.0f} marks ({p}%)  |  {sub.get("language","").upper()}  |  {dt}',
                styles['CJSubSection']
            ),
        ]))

        if not rpts:
            story.append(Paragraph('No test case data.', styles['CJSmall']))
            story.append(spacer(6))
            continue

        tc_data = [['TC#', 'Input', 'Expected Output', 'Your Output', 'Result']]
        for i, r in enumerate(rpts, 1):
            result = r.get('result', 'FAIL')
            tc_data.append([
                str(i),
                (r.get('input','') or '(none)')[:40],
                (r.get('expected','') or '')[:40],
                (r.get('actual','') or '')[:40],
                result
            ])

        tc_tbl = Table(tc_data, colWidths=[25, 130, 130, 130, 45])
        tc_tbl.setStyle(tbl_style())
        for i, r in enumerate(rpts, 1):
            clr = GREEN if r.get('result') == 'PASS' else RED
            tc_tbl.setStyle(TableStyle([
                ('TEXTCOLOR', (4,i),(4,i), clr),
                ('FONTNAME',  (4,i),(4,i), 'Helvetica-Bold'),
                ('BACKGROUND',(0,i),(-1,i),
                 LIGHT_GR if r.get('result')=='PASS' else LIGHT_RED),
            ]))

        story += [tc_tbl, spacer(10)]

    # ── Recommendations ───────────────────────────────────────────────────────
    story.append(section_heading('4.  Recommendations', styles))

    recs = []
    if score_pct < 50:
        recs.append('Your overall score is below 50%. Focus on understanding problem statements carefully before coding.')
    if fail_count > pass_count:
        recs.append('More test cases are failing than passing. Review your output format — check for extra spaces or wrong case.')
    if len(done) < 3:
        recs.append('You have few submissions. Practice more questions to improve your skills.')
    if any(s.get('language','').lower() == 'python' for s in done):
        recs.append('Good use of Python. Consider also practising in C++ or Java for compiled language experience.')
    if score_pct >= 80:
        recs.append('Excellent performance! Try harder questions to challenge yourself further.')

    if not recs:
        recs.append('Keep up the good work and continue practicing regularly.')

    for rec in recs:
        story.append(Paragraph(f'• {rec}', styles['CJBody']))
    story.append(spacer(16))

    # ── Footer / build ────────────────────────────────────────────────────────
    def add_footer(canvas, doc):
        canvas.saveState()
        canvas.setFont('Helvetica', 7)
        canvas.setFillColor(GREY_TEXT)
        canvas.drawString(30, 20, f'CodeJudge Personal Report  |  {name}  |  {gen_at}')
        canvas.drawRightString(W - 30, 20, f'Page {doc.page}')
        canvas.line(30, 28, W - 30, 28)
        canvas.restoreState()

    doc = SimpleDocTemplate(
        buf, pagesize=A4,
        leftMargin=30, rightMargin=30,
        topMargin=30, bottomMargin=30,
        title=f'CodeJudge Report - {name}'
    )
    doc.build(story, onFirstPage=add_footer, onLaterPages=add_footer)
    return buf.getvalue()