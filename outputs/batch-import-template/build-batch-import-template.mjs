import fs from "node:fs/promises";
import { SpreadsheetFile, Workbook } from "@oai/artifact-tool";

const outputDir = "D:/laravel/ZakiAuto/outputs/batch-import-template";
await fs.mkdir(outputDir, { recursive: true });

const workbook = Workbook.create();
const sheet = workbook.worksheets.add("Batch Import");
const notes = workbook.worksheets.add("Notes");

sheet.showGridLines = false;
notes.showGridLines = false;

const headers = [
  "العلامة التجارية",
  "الموديل",
  "الفئة (Finition)",
  "سنة الصنع",
  "اللون",
  "رقم الهيكل (VIN)",
  "سعر الشراء (تكلفة)",
  "رقم التتبع",
  "اسم العميل الكامل",
  "رقم جواز السفر",
  "رقم البطاقة الوطنية",
  "تكلفة الشحن",
  "تاريخ الوصول",
];

const rows = [
  ["Toyota", "Corolla", "Active", 2021, "أبيض", "JTDBR32E720123456", 13500, "TRK-2026-001", "أحمد بن يوسف", "P1234567", "NID-100001", 850, new Date("2026-08-15")],
  ["Hyundai", "Tucson", "Comfort", 2022, "رمادي", "KM8J33A45NU123456", 19800, "TRK-2026-002", "سارة مراد", "P7654321", "NID-100002", 1100, new Date("2026-08-20")],
  ["Kia", "Sportage", "GT Line", 2023, "أسود", "KNDPM3AC7P7123456", 22400, "TRK-2026-003", "محمد علي", "P2468101", "NID-100003", 1200, new Date("2026-09-02")],
  ["Renault", "Clio", "Life", 2020, "أزرق", "VF1RJA004L1234567", 9700, "TRK-2026-004", "ليلى منصور", "P1357911", "NID-100004", 650, new Date("2026-09-10")],
  ["Peugeot", "3008", "Allure", 2021, "فضي", "VF3M45GFRMS123456", 21000, "TRK-2026-005", "يوسف قادري", "P1122334", "NID-100005", 1050, new Date("2026-09-18")],
];

sheet.getRange("A1:M1").values = [headers];
sheet.getRange("A2:M6").values = rows;

sheet.getRange("A1:M1").format = {
  fill: "#1F4E79",
  font: { bold: true, color: "#FFFFFF" },
  horizontalAlignment: "center",
  verticalAlignment: "center",
  wrapText: true,
};
sheet.getRange("A1:M6").format.borders = {
  insideHorizontal: { style: "thin", color: "#D7DEE8" },
  top: { style: "medium", color: "#1F4E79" },
  bottom: { style: "thin", color: "#9EB6CE" },
};
sheet.getRange("A2:M6").format = {
  font: { color: "#1F2937" },
  verticalAlignment: "center",
};
sheet.getRange("D2:D101").format.numberFormat = "0";
sheet.getRange("G2:G101").format.numberFormat = "#,##0.00";
sheet.getRange("L2:L101").format.numberFormat = "#,##0.00";
sheet.getRange("M2:M101").format.numberFormat = "yyyy-mm-dd";
sheet.getRange("A:M").format.autofitColumns();
sheet.getRange("A1:M1").format.rowHeightPx = 42;
sheet.freezePanes.freezeRows(1);

const widthMap = [
  ["A:A", 18], ["B:B", 18], ["C:C", 18], ["D:D", 12], ["E:E", 14],
  ["F:F", 24], ["G:G", 18], ["H:H", 18], ["I:I", 22], ["J:J", 18],
  ["K:K", 22], ["L:L", 16], ["M:M", 16],
];
for (const [range, width] of widthMap) {
  sheet.getRange(range).format.columnWidth = width;
}

notes.getRange("A1:D1").merge();
notes.getRange("A1").values = [["ملاحظات استيراد Batch"]];
notes.getRange("A1").format = {
  fill: "#1F4E79",
  font: { bold: true, color: "#FFFFFF", size: 14 },
  horizontalAlignment: "center",
};
notes.getRange("A3:D8").values = [
  ["القاعدة", "التفصيل", "", ""],
  ["الصف الأول", "عناوين فقط. يبدأ النظام قراءة السيارات من الصف الثاني.", "", ""],
  ["أعمدة إلزامية", "العلامة التجارية، الموديل، سنة الصنع، سعر الشراء، اسم العميل الكامل.", "", ""],
  ["التواريخ", "يفضل إدخال تاريخ الوصول بصيغة yyyy-mm-dd مثل 2026-08-15.", "", ""],
  ["VIN", "يجب ألا يكون رقم الهيكل مكررًا في النظام إذا تم إدخاله.", "", ""],
  ["بيانات Batch", "supplier_id و purchase_date و total_cost_foreign يتم إرسالها من شاشة الرفع، وليست ضمن هذا الملف.", "", ""],
];
notes.getRange("A3:B3").format = {
  fill: "#D9EAF7",
  font: { bold: true, color: "#0F3554" },
};
notes.getRange("A3:B8").format.borders = { preset: "all", style: "thin", color: "#D7DEE8" };
notes.getRange("A:B").format.autofitColumns();
notes.getRange("A:A").format.columnWidth = 20;
notes.getRange("B:B").format.columnWidth = 78;
notes.getRange("B3:B8").format.wrapText = true;

const preview = await workbook.render({
  sheetName: "Batch Import",
  range: "A1:M8",
  scale: 1,
  format: "png",
});
await fs.writeFile(`${outputDir}/batch_import_template_preview.png`, new Uint8Array(await preview.arrayBuffer()));

const inspect = await workbook.inspect({
  kind: "table",
  range: "Batch Import!A1:M6",
  include: "values,formulas",
  tableMaxRows: 8,
  tableMaxCols: 13,
});
console.log(inspect.ndjson);

const errors = await workbook.inspect({
  kind: "match",
  searchTerm: "#REF!|#DIV/0!|#VALUE!|#NAME\\?|#N/A",
  options: { useRegex: true, maxResults: 50 },
  summary: "formula error scan",
});
console.log(errors.ndjson);

const output = await SpreadsheetFile.exportXlsx(workbook);
await output.save(`${outputDir}/batch_import_template_with_sample_data.xlsx`);
