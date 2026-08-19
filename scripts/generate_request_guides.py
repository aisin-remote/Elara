from pathlib import Path
from shutil import copy2

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfbase import pdfmetrics
from reportlab.platypus import KeepTogether, Paragraph, SimpleDocTemplate, Spacer, Table, TableStyle


ROOT = Path(__file__).resolve().parents[1]
OUTPUT_DIR = ROOT / "output" / "pdf"
PUBLIC_DIR = ROOT / "public" / "docs"
FONT_DIR = Path("C:/Windows/Fonts")

NAVY = colors.HexColor("#0f172a")
BLUE = colors.HexColor("#2563eb")
SKY = colors.HexColor("#38bdf8")
SLATE = colors.HexColor("#475569")
MUTED = colors.HexColor("#64748b")
LINE = colors.HexColor("#dbe3ee")
PALE = colors.HexColor("#eff6ff")
WHITE = colors.white


GUIDES = [
    {
        "filename": "elara-feature-request-guide.pdf",
        "title": "Feature Request Guide",
        "subtitle": "How a change to an existing system moves from request to delivery",
        "use_when": "Use Feature when you need a change, improvement, or addition to a system that already exists.",
        "steps": [
            ("Describe the need", "Explain the current condition, target condition, and benefit. Focus on the need rather than prescribing how ITD should build it.", "You"),
            ("Supervisor review", "The reviewer approves it, rejects it with a reason, or asks you for more information.", "Supervisor"),
            ("Capacity scheduling", "Approved requests are placed into the delivery schedule using real team capacity.", "Automatic"),
            ("Work planning", "ITD breaks the request into estimated tasks and reviews the plan before work starts.", "ITD"),
            ("Validate the result", "Work that only you can judge pauses until you confirm that the result is correct.", "You"),
            ("Delivered", "After the final task and required validation are complete, the request moves to History.", "ITD"),
        ],
        "tips": [
            "Describe the current condition instead of prescribing a technical solution.",
            "Include how often the issue happens and how much time it consumes.",
            "Choose the system you actually use, even if you are unsure where the change belongs.",
            "Use urgent only when waiting creates real operational harm.",
        ],
        "validation": True,
        "follow": "Monitor progress from My requests. Once delivery starts, use Timeline to see the feature schedule. Respond to validation items under Waiting on me.",
    },
    {
        "filename": "elara-project-request-guide.pdf",
        "title": "Project Proposal Guide",
        "subtitle": "How a new project moves from business case to delivery",
        "use_when": "Use Project when the work creates a new system, major initiative, or delivery scope that needs formal scoping and approval.",
        "steps": [
            ("Build the business case", "Describe the background, pain point, objectives, before-and-after process, benefits, costs, and expected return.", "You"),
            ("Department review", "When required by job rank, the department manager or coordinator reviews the proposal first.", "Department"),
            ("Scoping meeting", "ITD meets with you to clarify scope. No ITD signature can be recorded before this meeting.", "You and ITD"),
            ("First ITD signature", "An ITD supervisor reviews the scoped proposal and records the first decision.", "ITD supervisor"),
            ("Second ITD signature", "A different ITD manager records the second decision. One person cannot provide both signatures.", "ITD manager"),
            ("Planning and delivery", "After approval, the project is created, scheduled against capacity, and broken into tasks.", "ITD"),
        ],
        "tips": [
            "Use measurable objectives whenever possible.",
            "Describe the current process honestly, including manual workarounds.",
            "Include tangible benefits such as saved hours and intangible benefits such as better control.",
            "Treat the target date as a preference until capacity planning confirms the schedule.",
        ],
        "validation": True,
        "follow": "Monitor the proposal from My requests. Once delivery starts, use Timeline to open the project schedule and its task timeline.",
    },
    {
        "filename": "elara-supporting-request-guide.pdf",
        "title": "Supporting Request Guide",
        "subtitle": "How operational support moves from request to completion",
        "use_when": "Use Supporting for operational work outside a system feature or project, such as a presentation, printer issue, account problem, or network check.",
        "steps": [
            ("Describe the support needed", "Explain the expected result and include the relevant device, file, account, or location.", "You"),
            ("ITD triage", "ITD reviews the request, confirms its priority, and assigns the right team member.", "ITD"),
            ("Support in progress", "The assignee works on the request and keeps its status up to date.", "ITD"),
            ("Completed", "The completed request remains available in your request history.", "ITD"),
        ],
        "tips": [
            "Use Supporting only when the work does not change a system or require a new project.",
            "Include device names, room locations, file formats, or account names when relevant.",
            "Choose a realistic needed-by date and use high priority only for real operational impact.",
            "Describe the expected result so ITD knows when the request is complete.",
        ],
        "validation": False,
        "follow": "Monitor assignment, status, and completion from My requests under the Supporting tab.",
    },
    {
        "locale": "id",
        "filename": "elara-feature-request-guide-id.pdf",
        "title": "Panduan Permintaan Fitur",
        "subtitle": "Alur perubahan sistem dari permintaan hingga selesai",
        "use_when": "Gunakan Fitur untuk meminta perubahan, peningkatan, atau tambahan pada sistem yang sudah tersedia.",
        "steps": [
            ("Jelaskan kebutuhan", "Tuliskan kondisi saat ini, kondisi yang diharapkan, dan manfaatnya. Fokus pada kebutuhan, bukan cara ITD harus membangunnya.", "Anda"),
            ("Tinjauan supervisor", "Peninjau menyetujui, menolak dengan alasan, atau meminta informasi tambahan.", "Supervisor"),
            ("Penjadwalan kapasitas", "Permintaan yang disetujui ditempatkan pada jadwal berdasarkan kapasitas tim yang tersedia.", "Otomatis"),
            ("Perencanaan pekerjaan", "ITD memecah permintaan menjadi task beserta estimasinya dan meninjau rencana sebelum pekerjaan dimulai.", "ITD"),
            ("Validasi hasil", "Pekerjaan yang hanya dapat Anda nilai menunggu sampai Anda memastikan hasilnya sudah benar.", "Anda"),
            ("Selesai", "Setelah task terakhir dan validasi selesai, permintaan dipindahkan ke History.", "ITD"),
        ],
        "tips": [
            "Jelaskan kondisi saat ini, bukan langsung menentukan solusi teknis.",
            "Sertakan frekuensi masalah dan waktu yang terbuang karenanya.",
            "Pilih sistem yang benar-benar digunakan meskipun Anda belum tahu letak perubahan yang tepat.",
            "Gunakan prioritas mendesak hanya jika penundaan menimbulkan dampak operasional nyata.",
        ],
        "validation": True,
        "follow": "Pantau progres melalui My requests. Setelah pengerjaan dimulai, gunakan Timeline untuk melihat jadwal fitur. Tanggapi validasi melalui Waiting on me.",
    },
    {
        "locale": "id",
        "filename": "elara-project-request-guide-id.pdf",
        "title": "Panduan Pengajuan Proyek",
        "subtitle": "Alur proyek baru dari business case hingga delivery",
        "use_when": "Gunakan Proyek untuk sistem baru, inisiatif besar, atau ruang lingkup pekerjaan yang memerlukan scoping dan persetujuan formal.",
        "steps": [
            ("Susun business case", "Jelaskan latar belakang, masalah, tujuan, proses sebelum dan sesudah, manfaat, biaya, serta hasil yang diharapkan.", "Anda"),
            ("Tinjauan departemen", "Jika diwajibkan oleh job rank, manajer atau koordinator departemen meninjau pengajuan terlebih dahulu.", "Departemen"),
            ("Scoping meeting", "ITD bertemu dengan Anda untuk memperjelas ruang lingkup. Persetujuan ITD belum dapat diberikan sebelum pertemuan ini.", "Anda dan ITD"),
            ("Persetujuan ITD pertama", "Supervisor ITD meninjau proposal hasil scoping dan mencatat keputusan pertama.", "Supervisor ITD"),
            ("Persetujuan ITD kedua", "Manajer ITD yang berbeda mencatat keputusan kedua. Satu orang tidak boleh memberikan kedua persetujuan.", "Manajer ITD"),
            ("Perencanaan dan delivery", "Setelah disetujui, proyek dibuat, dijadwalkan berdasarkan kapasitas, lalu dipecah menjadi task.", "ITD"),
        ],
        "tips": [
            "Gunakan tujuan yang terukur jika memungkinkan.",
            "Jelaskan proses saat ini dengan jujur, termasuk pekerjaan manual sementara.",
            "Sertakan manfaat terukur seperti penghematan waktu dan manfaat nonfinansial seperti kontrol yang lebih baik.",
            "Anggap target tanggal sebagai preferensi sampai capacity planning menetapkan jadwal.",
        ],
        "validation": True,
        "follow": "Pantau proposal melalui My requests. Setelah delivery dimulai, gunakan Timeline untuk membuka jadwal proyek dan timeline task-nya.",
    },
    {
        "locale": "id",
        "filename": "elara-supporting-request-guide-id.pdf",
        "title": "Panduan Permintaan Supporting",
        "subtitle": "Alur dukungan operasional dari permintaan hingga selesai",
        "use_when": "Gunakan Supporting untuk pekerjaan operasional di luar fitur atau proyek, seperti presentasi, printer, akun, atau pemeriksaan jaringan.",
        "steps": [
            ("Jelaskan dukungan yang dibutuhkan", "Tuliskan hasil yang diharapkan serta perangkat, file, akun, atau lokasi yang berkaitan.", "Anda"),
            ("Triage ITD", "ITD meninjau permintaan, memastikan prioritas, dan menentukan anggota tim yang tepat.", "ITD"),
            ("Dukungan dikerjakan", "PIC mengerjakan permintaan dan memperbarui statusnya secara berkala.", "ITD"),
            ("Selesai", "Permintaan yang selesai tetap dapat dilihat dalam riwayat permintaan Anda.", "ITD"),
        ],
        "tips": [
            "Gunakan Supporting hanya untuk pekerjaan operasional yang tidak mengubah sistem atau membutuhkan proyek baru.",
            "Sertakan nama perangkat, lokasi ruangan, format file, atau nama akun jika relevan.",
            "Pilih tanggal kebutuhan yang realistis dan gunakan prioritas tinggi hanya untuk dampak operasional nyata.",
            "Jelaskan hasil akhir agar ITD mengetahui kapan permintaan dapat dinyatakan selesai.",
        ],
        "validation": False,
        "follow": "Pantau PIC, status, dan penyelesaian melalui My requests pada tab Supporting.",
    },
]


LABELS = {
    "en": {
        "use_when": "When to use this request",
        "flow": "Request-to-delivery flow",
        "tips": "Before you submit",
        "validation": "Your validation deadline",
        "validation_body": "When ITD finishes work that only you can validate, it appears under <b>Waiting on me</b>. Respond within the validation window shown in Elara. No response cancels the request and releases the capacity slot. Requesting changes is always better than not responding.",
        "follow": "Where to follow progress",
        "footer": "Elara - Request-to-delivery workspace",
    },
    "id": {
        "use_when": "Kapan permintaan ini digunakan",
        "flow": "Alur permintaan hingga selesai",
        "tips": "Sebelum mengirim permintaan",
        "validation": "Batas waktu validasi Anda",
        "validation_body": "Saat ITD menyelesaikan pekerjaan yang hanya dapat Anda validasi, item akan muncul di <b>Waiting on me</b>. Berikan respons dalam masa validasi yang ditampilkan di Elara. Tanpa respons, permintaan dibatalkan dan slot kapasitas dilepas. Meminta revisi selalu lebih baik daripada tidak merespons.",
        "follow": "Tempat memantau progres",
        "footer": "Elara - Ruang kerja permintaan hingga penyelesaian",
    },
}


def register_fonts() -> tuple[str, str]:
    regular = FONT_DIR / "segoeui.ttf"
    bold = FONT_DIR / "segoeuib.ttf"
    if regular.exists() and bold.exists():
        pdfmetrics.registerFont(TTFont("Elara", str(regular)))
        pdfmetrics.registerFont(TTFont("Elara-Bold", str(bold)))
        return "Elara", "Elara-Bold"
    return "Helvetica", "Helvetica-Bold"


def footer(canvas, doc):
    canvas.saveState()
    canvas.setStrokeColor(LINE)
    canvas.line(18 * mm, 14 * mm, A4[0] - 18 * mm, 14 * mm)
    canvas.setFont(doc.regular_font, 7.5)
    canvas.setFillColor(MUTED)
    canvas.drawString(18 * mm, 9 * mm, doc.footer_text)
    canvas.drawRightString(A4[0] - 18 * mm, 9 * mm, f"Page {doc.page}")
    canvas.restoreState()


def build_guide(guide: dict, regular_font: str, bold_font: str) -> Path:
    output = OUTPUT_DIR / guide["filename"]
    labels = LABELS[guide.get("locale", "en")]
    doc = SimpleDocTemplate(
        str(output),
        pagesize=A4,
        rightMargin=18 * mm,
        leftMargin=18 * mm,
        topMargin=16 * mm,
        bottomMargin=20 * mm,
        title=f"Elara - {guide['title']}",
        author="Elara",
    )
    doc.regular_font = regular_font
    doc.footer_text = labels["footer"]
    styles = getSampleStyleSheet()
    title = ParagraphStyle("Title", parent=styles["Title"], fontName=bold_font, fontSize=20, leading=24, textColor=WHITE, spaceAfter=3)
    subtitle = ParagraphStyle("Subtitle", parent=styles["BodyText"], fontName=regular_font, fontSize=8.5, leading=12, textColor=colors.HexColor("#cbd5e1"))
    logo = ParagraphStyle("Logo", parent=styles["Title"], fontName=bold_font, fontSize=20, leading=26, alignment=TA_CENTER, textColor=WHITE)
    section = ParagraphStyle("Section", parent=styles["Heading2"], fontName=bold_font, fontSize=11.5, leading=14, textColor=NAVY, spaceBefore=2, spaceAfter=6)
    body = ParagraphStyle("Body", parent=styles["BodyText"], fontName=regular_font, fontSize=8.8, leading=12.5, textColor=SLATE)
    step_title = ParagraphStyle("StepTitle", parent=body, fontName=bold_font, fontSize=9.5, leading=12, textColor=NAVY)
    step_body = ParagraphStyle("StepBody", parent=body, fontSize=8.3, leading=11.5, textColor=MUTED)
    badge = ParagraphStyle("Badge", parent=body, fontName=bold_font, fontSize=7.2, leading=9, textColor=BLUE, alignment=TA_CENTER)
    number = ParagraphStyle("Number", parent=body, fontName=bold_font, fontSize=9, leading=17, textColor=WHITE, alignment=TA_CENTER)
    bullet = ParagraphStyle("Bullet", parent=body, leftIndent=10, firstLineIndent=-8, bulletIndent=0, spaceAfter=3)

    story = []
    logo_box = Table([[Paragraph("E", logo)]], colWidths=[13 * mm], rowHeights=[13 * mm])
    logo_box.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), BLUE),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("BOX", (0, 0), (-1, -1), 0.5, SKY),
    ]))
    header = Table([[logo_box, [Paragraph(guide["title"], title), Paragraph(guide["subtitle"], subtitle)]]], colWidths=[18 * mm, 145 * mm])
    header.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), NAVY),
        ("LEFTPADDING", (0, 0), (0, 0), 4 * mm),
        ("RIGHTPADDING", (0, 0), (0, 0), 1 * mm),
        ("LEFTPADDING", (1, 0), (1, 0), 3 * mm),
        ("RIGHTPADDING", (1, 0), (1, 0), 5 * mm),
        ("TOPPADDING", (0, 0), (-1, -1), 5 * mm),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5 * mm),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
    ]))
    story.extend([header, Spacer(1, 7 * mm)])

    story.append(Paragraph(labels["use_when"], section))
    use_box = Table([[Paragraph(guide["use_when"], body)]], colWidths=[163 * mm])
    use_box.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), PALE),
        ("BOX", (0, 0), (-1, -1), 0.6, colors.HexColor("#bfdbfe")),
        ("LEFTPADDING", (0, 0), (-1, -1), 4 * mm),
        ("RIGHTPADDING", (0, 0), (-1, -1), 4 * mm),
        ("TOPPADDING", (0, 0), (-1, -1), 3 * mm),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 3 * mm),
    ]))
    story.extend([use_box, Spacer(1, 5 * mm), Paragraph(labels["flow"], section)])

    for index, (step_name, description, owner) in enumerate(guide["steps"], 1):
        number_box = Table([[Paragraph(str(index), number)]], colWidths=[8 * mm], rowHeights=[8 * mm])
        number_box.setStyle(TableStyle([
            ("BACKGROUND", (0, 0), (-1, -1), BLUE if owner in {"You", "Anda"} else colors.HexColor("#94a3b8")),
            ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ]))
        owner_box = Table([[Paragraph(owner, badge)]], colWidths=[26 * mm])
        owner_box.setStyle(TableStyle([
            ("BACKGROUND", (0, 0), (-1, -1), PALE),
            ("BOX", (0, 0), (-1, -1), 0.5, colors.HexColor("#bfdbfe")),
            ("TOPPADDING", (0, 0), (-1, -1), 1.5 * mm),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 1.5 * mm),
        ]))
        copy = [Paragraph(step_name, step_title), Paragraph(description, step_body)]
        row = Table([[number_box, copy, owner_box]], colWidths=[12 * mm, 121 * mm, 30 * mm])
        row.setStyle(TableStyle([
            ("VALIGN", (0, 0), (-1, -1), "TOP"),
            ("LEFTPADDING", (0, 0), (-1, -1), 0),
            ("RIGHTPADDING", (0, 0), (-1, -1), 2 * mm),
            ("TOPPADDING", (0, 0), (-1, -1), 1.5 * mm),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 2.2 * mm),
            ("LINEBELOW", (1, 0), (2, 0), 0.4, LINE),
        ]))
        story.append(KeepTogether(row))

    story.extend([Spacer(1, 4 * mm), Paragraph(labels["tips"], section)])
    for tip in guide["tips"]:
        story.append(Paragraph(f"- {tip}", bullet))

    if guide["validation"]:
        story.extend([
            Spacer(1, 3 * mm),
            Paragraph(labels["validation"], section),
            Paragraph(labels["validation_body"], body),
        ])

    story.extend([
        Spacer(1, 4 * mm),
        Paragraph(labels["follow"], section),
        Paragraph(guide["follow"], body),
    ])
    doc.build(story, onFirstPage=footer, onLaterPages=footer)
    return output


def main() -> None:
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    PUBLIC_DIR.mkdir(parents=True, exist_ok=True)
    regular_font, bold_font = register_fonts()
    outputs = [build_guide(guide, regular_font, bold_font) for guide in GUIDES]
    for output in outputs:
        copy2(output, PUBLIC_DIR / output.name)
    print("\n".join(str(output) for output in outputs))


if __name__ == "__main__":
    main()
