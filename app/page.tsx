"use client";

import { FormEvent, useMemo, useState } from "react";

type View = "ringkasan" | "input" | "riwayat" | "material";
type Role = "teknisi" | "pimpinan";
type JobStatus = "Selesai" | "Pending";
type WorkType = "PDA" | "IH" | "HSI" | "PT2" | "EXPAND ODP";

type Job = {
  id: string;
  date: string;
  customer: string;
  type: WorkType;
  status: JobStatus;
  reporterName: string;
  reporterNik: string;
  technician1Name: string;
  technician1Nik: string;
  technician2Name: string;
  technician2Nik: string;
};

const initialJobs: Job[] = [
  {
    id: "WO-260720-018",
    date: "20 Jul 2026",
    customer: "Budi Santoso",
    type: "HSI",
    status: "Selesai",
    reporterName: "Andi Pratama",
    reporterNik: "TK-24017",
    technician1Name: "Andi Pratama",
    technician1Nik: "TK-24017",
    technician2Name: "Rizky Saputra",
    technician2Nik: "TK-24022",
  },
  {
    id: "WO-260720-012",
    date: "20 Jul 2026",
    customer: "CV Sinar Jaya",
    type: "PDA",
    status: "Selesai",
    reporterName: "Andi Pratama",
    reporterNik: "TK-24017",
    technician1Name: "Andi Pratama",
    technician1Nik: "TK-24017",
    technician2Name: "Dimas Akbar",
    technician2Nik: "TK-24009",
  },
  {
    id: "WO-190720-044",
    date: "19 Jul 2026",
    customer: "Rina Maharani",
    type: "IH",
    status: "Pending",
    reporterName: "Andi Pratama",
    reporterNik: "TK-24017",
    technician1Name: "Andi Pratama",
    technician1Nik: "TK-24017",
    technician2Name: "Rizky Saputra",
    technician2Nik: "TK-24022",
  },
  {
    id: "WO-190720-031",
    date: "19 Jul 2026",
    customer: "Toko Cahaya Baru",
    type: "EXPAND ODP",
    status: "Selesai",
    reporterName: "Rizky Saputra",
    reporterNik: "TK-24022",
    technician1Name: "Rizky Saputra",
    technician1Nik: "TK-24022",
    technician2Name: "Dimas Akbar",
    technician2Nik: "TK-24009",
  },
];

const materialRows = [
  { name: "Kabel drop", unit: "meter", used: 186, stock: 320, state: "Aman" },
  { name: "Konektor SC/UPC", unit: "pcs", used: 24, stock: 38, state: "Aman" },
  { name: "Patch cord", unit: "pcs", used: 8, stock: 12, state: "Menipis" },
  { name: "Kabel indoor", unit: "meter", used: 64, stock: 145, state: "Aman" },
];

const navItems: { id: View; label: string; mark: string }[] = [
  { id: "ringkasan", label: "Ringkasan", mark: "01" },
  { id: "input", label: "Input pekerjaan", mark: "02" },
  { id: "riwayat", label: "Riwayat kerja", mark: "03" },
  { id: "material", label: "Material", mark: "04" },
];

export default function Home() {
  const [view, setView] = useState<View>("ringkasan");
  const [role, setRole] = useState<Role>("teknisi");
  const [jobs, setJobs] = useState(initialJobs);
  const [filter, setFilter] = useState("Semua");
  const [notice, setNotice] = useState("");

  const visibleJobs = useMemo(() => {
    const owned = role === "teknisi" ? jobs.filter((job) => job.technician1Name === "Andi Pratama" || job.technician2Name === "Andi Pratama") : jobs;
    return filter === "Semua" ? owned : owned.filter((job) => job.status === filter || job.type === filter);
  }, [filter, jobs, role]);

  const completed = visibleJobs.filter((job) => job.status === "Selesai");
  function saveJob(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const type = form.get("type") as WorkType;
    const psDate = String(form.get("psDate"));
    const newJob: Job = {
      id: String(form.get("workOrder")),
      date: new Intl.DateTimeFormat("id-ID", { day: "2-digit", month: "short", year: "numeric", timeZone: "UTC" }).format(new Date(`${psDate}T00:00:00Z`)),
      customer: String(form.get("customer")),
      type,
      status: "Selesai",
      reporterName: String(form.get("name")),
      reporterNik: String(form.get("nik")),
      technician1Name: String(form.get("technician1Name")),
      technician1Nik: String(form.get("technician1Nik")),
      technician2Name: String(form.get("technician2Name")),
      technician2Nik: String(form.get("technician2Nik")),
    };
    setJobs((current) => [newJob, ...current]);
    setNotice("Pekerjaan berhasil disimpan ke rekap Anda.");
    event.currentTarget.reset();
    window.setTimeout(() => setNotice(""), 3200);
  }

  return (
    <main className="app-shell">
      <aside className="sidebar">
        <button className="brand" type="button" onClick={() => setView("ringkasan")} aria-label="Buka ringkasan">
          <span className="brand-mark">IH</span>
          <span><strong>IndiHome Field</strong><small>Monitor tim lapangan</small></span>
        </button>

        <div className="role-switch" aria-label="Pilih tampilan akun">
          <button className={role === "teknisi" ? "active" : ""} onClick={() => setRole("teknisi")}>Teknisi</button>
          <button className={role === "pimpinan" ? "active" : ""} onClick={() => setRole("pimpinan")}>Pimpinan</button>
        </div>

        <nav className="side-nav" aria-label="Navigasi utama">
          <p className="nav-label">MENU UTAMA</p>
          {navItems.map((item) => (
            <button key={item.id} className={view === item.id ? "active" : ""} onClick={() => setView(item.id)}>
              <span className="nav-mark">{item.mark}</span>{item.label}
            </button>
          ))}
        </nav>

        <div className="profile-card">
          <span className="avatar">AP</span>
          <span><strong>{role === "teknisi" ? "Andi Pratama" : "Dewi Larasati"}</strong><small>{role === "teknisi" ? "NIK TK-24017" : "Supervisor Area"}</small></span>
          <button title="Pengaturan akun" aria-label="Pengaturan akun">...</button>
        </div>
      </aside>

      <section className="content">
        <header className="topbar">
          <div>
            <p className="eyebrow">SENIN, 20 JULI 2026</p>
            <h1>{pageTitle(view, role)}</h1>
          </div>
          <div className="top-actions">
            <span className="work-status"><i /> 08:00 - 17:00</span>
            <button className="primary-action" onClick={() => setView("input")}><span>+</span> Catat pekerjaan</button>
          </div>
        </header>

        {view === "ringkasan" && (
          <Dashboard role={role} jobs={visibleJobs} completed={completed.length} onHistory={() => setView("riwayat")} />
        )}

        {view === "input" && <JobForm onSubmit={saveJob} />}

        {view === "riwayat" && (
          <History jobs={visibleJobs} filter={filter} setFilter={setFilter} role={role} />
        )}

        {view === "material" && <Materials role={role} />}
      </section>

      <nav className="mobile-nav" aria-label="Navigasi seluler">
        {navItems.map((item) => (
          <button key={item.id} className={view === item.id ? "active" : ""} onClick={() => setView(item.id)}>
            <span>{item.mark}</span>{item.label.split(" ")[0]}
          </button>
        ))}
      </nav>

      {notice && <div className="toast" role="status"><span>OK</span>{notice}</div>}
    </main>
  );
}

function pageTitle(view: View, role: Role) {
  if (view === "ringkasan") return role === "teknisi" ? "Selamat bekerja, Andi" : "Ringkasan kinerja tim";
  if (view === "input") return "Catat pekerjaan hari ini";
  if (view === "riwayat") return role === "teknisi" ? "Rekap hasil kerja Anda" : "Riwayat pekerjaan tim";
  return "Pemakaian material";
}

function Dashboard({ role, jobs, completed, onHistory }: { role: Role; jobs: Job[]; completed: number; onHistory: () => void }) {
  const teamMode = role === "pimpinan";
  return (
    <div className="dashboard-grid">
      <section className="stats-grid">
        <article className="stat-card accent-card">
          <div className="stat-head"><span>Pekerjaan selesai</span><b>MTD</b></div>
          <strong>{teamMode ? 128 : Math.max(18, completed)}</strong>
          <p><span className="positive">+12%</span> dari bulan lalu</p>
        </article>
        <article className="stat-card">
          <div className="stat-head"><span>{teamMode ? "Teknisi aktif" : "Target bulanan"}</span><b>JUL</b></div>
          <strong>{teamMode ? "14" : "18 / 22"}</strong>
          <p>{teamMode ? "2 teknisi sedang WFH" : "4 pekerjaan lagi untuk target"}</p>
        </article>
        <article className="stat-card">
          <div className="stat-head"><span>Jenis terbanyak</span><b>PS</b></div>
          <strong>{teamMode ? "PDA" : "HSI"}</strong>
          <p>Periode 1 - 31 Juli</p>
        </article>
        <article className="stat-card">
          <div className="stat-head"><span>Tingkat penyelesaian</span><b>%</b></div>
          <strong>{teamMode ? "92%" : "90%"}</strong>
          <div className="meter"><i style={{ width: teamMode ? "92%" : "90%" }} /></div>
        </article>
      </section>

      <section className="panel performance-panel">
        <div className="panel-heading">
          <div><p className="eyebrow">7 HARI TERAKHIR</p><h2>Aktivitas pekerjaan</h2></div>
          <span className="legend"><i /> Selesai</span>
        </div>
        <div className="chart" aria-label="Grafik aktivitas pekerjaan tujuh hari terakhir">
          {[42, 70, 54, 88, 62, 96, 76].map((height, index) => (
            <div className="bar-column" key={height + index}>
              <span className="bar-value">{teamMode ? [14, 20, 17, 24, 19, 27, 23][index] : [2, 4, 3, 5, 3, 6, 4][index]}</span>
              <i style={{ height: `${height}%` }} className={index === 5 ? "peak" : ""} />
              <small>{["Sen", "Sel", "Rab", "Kam", "Jum", "Sab", "Min"][index]}</small>
            </div>
          ))}
        </div>
      </section>

      <aside className="panel agenda-panel">
        <div className="panel-heading"><div><p className="eyebrow">HARI INI</p><h2>Agenda kerja</h2></div><span className="count-badge">3</span></div>
        <div className="agenda-list">
          <article><time>08:00</time><div><strong>Briefing pagi</strong><span>Basecamp Area Timur</span></div><b className="done">Selesai</b></article>
          <article><time>09:30</time><div><strong>HSI</strong><span>WO-260720-018</span></div><b>Berjalan</b></article>
          <article><time>14:00</time><div><strong>EXPAND ODP</strong><span>WO-260720-031</span></div><b className="muted">Berikutnya</b></article>
        </div>
        <div className="schedule-note"><span>WFH</span><p><strong>Rabu bekerja dari rumah</strong>Jam kerja tetap 08:00 - 17:00</p></div>
      </aside>

      <section className="panel recent-panel">
        <div className="panel-heading"><div><p className="eyebrow">PEKERJAAN TERBARU</p><h2>{teamMode ? "Aktivitas seluruh tim" : "Hasil kerja Anda"}</h2></div><button className="text-action" onClick={onHistory}>Lihat semua</button></div>
        <JobTable jobs={jobs.slice(0, 4)} />
      </section>
    </div>
  );
}

function JobForm({ onSubmit }: { onSubmit: (event: FormEvent<HTMLFormElement>) => void }) {
  return (
    <div className="form-layout">
      <form className="panel job-form" onSubmit={onSubmit}>
        <div className="section-heading"><span>01</span><div><h2>Data pelapor dan pekerjaan</h2><p>Isi identitas pelapor sesuai data kerja lapangan.</p></div></div>
        <div className="form-grid">
          <label><span>Nama</span><input name="name" placeholder="Masukkan nama pelapor" required /></label>
          <label><span>NIK</span><input name="nik" placeholder="Masukkan NIK pelapor" required /></label>
          <label className="full"><span>Jenis</span><select name="type" defaultValue="PDA" required><option>PDA</option><option>IH</option><option>HSI</option><option>PT2</option><option>EXPAND ODP</option></select></label>
        </div>

        <div className="section-heading second"><span>02</span><div><h2>Data teknisi</h2><p>Masukkan nama dan NIK dua teknisi yang mengerjakan.</p></div></div>
        <div className="form-grid">
          <label><span>Teknisi 1 - Nama</span><input name="technician1Name" placeholder="Nama teknisi 1" required /></label>
          <label><span>Teknisi 1 - NIK</span><input name="technician1Nik" placeholder="NIK teknisi 1" required /></label>
          <label><span>Teknisi 2 - Nama</span><input name="technician2Name" placeholder="Nama teknisi 2" required /></label>
          <label><span>Teknisi 2 - NIK</span><input name="technician2Nik" placeholder="NIK teknisi 2" required /></label>
        </div>

        <div className="section-heading second"><span>03</span><div><h2>Data pelanggan dan PS</h2><p>Pastikan nomor work order dan tanggal PS sudah sesuai.</p></div></div>
        <div className="form-grid">
          <label><span>Work Order</span><input name="workOrder" placeholder="Contoh: WO-260720-019" required /></label>
          <label><span>Nama Pelanggan</span><input name="customer" placeholder="Masukkan nama pelanggan" required /></label>
          <label className="full"><span>Tanggal PS</span><input name="psDate" type="date" defaultValue="2026-07-20" required /></label>
        </div>
        <div className="form-footer"><p>Data dapat dilihat kembali melalui menu Riwayat kerja.</p><button className="primary-action" type="submit">Simpan pekerjaan</button></div>
      </form>

      <aside className="form-aside">
        <article className="panel incentive-guide"><p className="eyebrow">JENIS PEKERJAAN</p><h2>Kategori laporan PS</h2><div><span>PDA</span><strong>01</strong></div><div><span>IH / HSI</span><strong>02</strong></div><div><span>PT2 / EXPAND ODP</span><strong>03</strong></div><small>Pilih kategori sesuai work order yang diterima oleh tim teknisi.</small></article>
        <article className="info-box"><b>Jam pelaporan</b><p>Catat pekerjaan setelah aktivitas lapangan selesai. Rekap harian ditutup pukul 21:00 WIB.</p></article>
      </aside>
    </div>
  );
}

function History({ jobs, filter, setFilter, role }: { jobs: Job[]; filter: string; setFilter: (value: string) => void; role: Role }) {
  return (
    <section className="panel history-panel">
      <div className="panel-heading history-heading">
        <div><p className="eyebrow">JULI 2026</p><h2>{role === "teknisi" ? "Semua pekerjaan Anda" : "Semua pekerjaan tim"}</h2></div>
        <div className="filters" aria-label="Filter pekerjaan">
          {["Semua", "Selesai", "Pending", "PDA", "IH", "HSI", "PT2", "EXPAND ODP"].map((item) => <button key={item} className={filter === item ? "active" : ""} onClick={() => setFilter(item)}>{item}</button>)}
        </div>
      </div>
      <div className="history-summary"><span><b>{jobs.length}</b> pekerjaan ditampilkan</span><span><b>{jobs.filter((job) => job.status === "Selesai").length}</b> selesai</span><span><b>{new Set(jobs.map((job) => job.type)).size}</b> jenis pekerjaan</span></div>
      <JobTable jobs={jobs} />
    </section>
  );
}

function Materials({ role }: { role: Role }) {
  return (
    <div className="material-layout">
      <section className="panel material-panel">
        <div className="panel-heading"><div><p className="eyebrow">PERIODE JULI 2026</p><h2>Rekap material {role === "teknisi" ? "Anda" : "seluruh tim"}</h2></div><button className="outline-action">Unduh rekap</button></div>
        <div className="material-table table-scroll"><table><thead><tr><th>Material</th><th>Satuan</th><th>Terpakai</th><th>Sisa stok</th><th>Status</th></tr></thead><tbody>{materialRows.map((row) => <tr key={row.name}><td><strong>{row.name}</strong></td><td>{row.unit}</td><td>{role === "teknisi" ? Math.ceil(row.used / 4) : row.used}</td><td>{row.stock}</td><td><span className={`stock ${row.state === "Menipis" ? "low" : ""}`}>{row.state}</span></td></tr>)}</tbody></table></div>
      </section>
      <aside className="panel usage-panel"><p className="eyebrow">KATEGORI TERBANYAK</p><h2>Pemakaian bulan ini</h2>{materialRows.slice(0, 3).map((row, index) => <div className="usage-row" key={row.name}><span><i style={{ background: ["#16664f", "#e0a42b", "#476d8f"][index] }} />{row.name}</span><strong>{Math.round((row.used / 282) * 100)}%</strong></div>)}</aside>
    </div>
  );
}

function JobTable({ jobs }: { jobs: Job[] }) {
  return (
    <div className="table-scroll"><table className="job-table"><thead><tr><th>Work order / Tanggal PS</th><th>Nama / NIK</th><th>Nama pelanggan</th><th>Jenis</th><th>Teknisi 1</th><th>Teknisi 2</th><th>Status</th></tr></thead><tbody>{jobs.map((job) => <tr key={job.id}><td><strong>{job.id}</strong><small>{job.date}</small></td><td><strong>{job.reporterName}</strong><small>{job.reporterNik}</small></td><td><strong>{job.customer}</strong></td><td><span className={`type ${job.type.toLowerCase().replace(" ", "-")}`}>{job.type}</span></td><td><strong>{job.technician1Name}</strong><small>{job.technician1Nik}</small></td><td><strong>{job.technician2Name}</strong><small>{job.technician2Nik}</small></td><td><span className={`status ${job.status.toLowerCase()}`}><i />{job.status}</span></td></tr>)}</tbody></table>{jobs.length === 0 && <div className="empty-state"><strong>Belum ada pekerjaan</strong><span>Coba pilih filter lainnya.</span></div>}</div>
  );
}
