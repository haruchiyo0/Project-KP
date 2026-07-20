"use client";

import { FormEvent, useMemo, useState } from "react";

type View = "ringkasan" | "input" | "riwayat" | "material";
type Role = "teknisi" | "pimpinan";
type JobStatus = "Selesai" | "Pending";

type Job = {
  id: string;
  date: string;
  customer: string;
  location: string;
  type: "Pasang Baru" | "Assurance" | "Maintenance";
  status: JobStatus;
  materials: string;
  incentive: number;
  technician: string;
};

const initialJobs: Job[] = [
  {
    id: "WO-260720-018",
    date: "20 Jul 2026",
    customer: "Budi Santoso",
    location: "Jl. Melati No. 18, Bandung",
    type: "Pasang Baru",
    status: "Selesai",
    materials: "Kabel drop 35 m, konektor 2",
    incentive: 20000,
    technician: "Andi Pratama",
  },
  {
    id: "WO-260720-012",
    date: "20 Jul 2026",
    customer: "CV Sinar Jaya",
    location: "Komplek Ruko Cendana B-7",
    type: "Assurance",
    status: "Selesai",
    materials: "Konektor 1, patch cord 1",
    incentive: 15000,
    technician: "Andi Pratama",
  },
  {
    id: "WO-190720-044",
    date: "19 Jul 2026",
    customer: "Rina Maharani",
    location: "Jl. Cisaranten Kulon No. 4",
    type: "Maintenance",
    status: "Pending",
    materials: "Kabel indoor 12 m",
    incentive: 0,
    technician: "Andi Pratama",
  },
  {
    id: "WO-190720-031",
    date: "19 Jul 2026",
    customer: "Toko Cahaya Baru",
    location: "Pasar Baru Blok C-12",
    type: "Pasang Baru",
    status: "Selesai",
    materials: "Kabel drop 28 m, konektor 2",
    incentive: 20000,
    technician: "Rizky Saputra",
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

const rupiah = (value: number) => `Rp${new Intl.NumberFormat("id-ID").format(value)}`;

export default function Home() {
  const [view, setView] = useState<View>("ringkasan");
  const [role, setRole] = useState<Role>("teknisi");
  const [jobs, setJobs] = useState(initialJobs);
  const [filter, setFilter] = useState("Semua");
  const [notice, setNotice] = useState("");

  const visibleJobs = useMemo(() => {
    const owned = role === "teknisi" ? jobs.filter((job) => job.technician === "Andi Pratama") : jobs;
    return filter === "Semua" ? owned : owned.filter((job) => job.status === filter || job.type === filter);
  }, [filter, jobs, role]);

  const completed = visibleJobs.filter((job) => job.status === "Selesai");
  const totalIncentive = completed.reduce((sum, job) => sum + job.incentive, 0);

  function saveJob(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const type = form.get("type") as Job["type"];
    const newJob: Job = {
      id: `WO-260720-${String(jobs.length + 19).padStart(3, "0")}`,
      date: "20 Jul 2026",
      customer: String(form.get("customer")),
      location: String(form.get("location")),
      type,
      status: form.get("status") as JobStatus,
      materials: String(form.get("materials")) || "Tidak ada material",
      incentive: form.get("status") === "Selesai" ? (type === "Pasang Baru" ? 20000 : type === "Assurance" ? 15000 : 10000) : 0,
      technician: "Andi Pratama",
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
          <span className="brand-mark">TK</span>
          <span><strong>TeknikKerja</strong><small>Monitor tim lapangan</small></span>
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
          <Dashboard role={role} jobs={visibleJobs} completed={completed.length} totalIncentive={totalIncentive} onHistory={() => setView("riwayat")} />
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

function Dashboard({ role, jobs, completed, totalIncentive, onHistory }: { role: Role; jobs: Job[]; completed: number; totalIncentive: number; onHistory: () => void }) {
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
          <div className="stat-head"><span>Estimasi insentif</span><b>IDR</b></div>
          <strong>{teamMode ? "Rp2,48 jt" : rupiah(Math.max(330000, totalIncentive))}</strong>
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
          <article><time>09:30</time><div><strong>Pasang baru</strong><span>Jl. Melati No. 18</span></div><b>Berjalan</b></article>
          <article><time>14:00</time><div><strong>Maintenance</strong><span>Komplek Ruko Cendana</span></div><b className="muted">Berikutnya</b></article>
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
        <div className="section-heading"><span>01</span><div><h2>Informasi pekerjaan</h2><p>Isi sesuai work order yang dikerjakan hari ini.</p></div></div>
        <div className="form-grid">
          <label><span>Tanggal pekerjaan</span><input name="date" type="date" defaultValue="2026-07-20" required /></label>
          <label><span>Jenis pekerjaan</span><select name="type" defaultValue="Pasang Baru"><option>Pasang Baru</option><option>Assurance</option><option>Maintenance</option></select></label>
          <label><span>Nama pelanggan / lokasi</span><input name="customer" placeholder="Contoh: Budi Santoso" required /></label>
          <label><span>Alamat pekerjaan</span><input name="location" placeholder="Masukkan alamat lengkap" required /></label>
          <label><span>Teknisi utama</span><input value="Andi Pratama - TK-24017" readOnly /></label>
          <label><span>Teknisi pendamping</span><select name="partner" defaultValue=""><option value="">Tanpa pendamping</option><option>Rizky Saputra - TK-24022</option><option>Dimas Akbar - TK-24009</option></select></label>
        </div>

        <div className="section-heading second"><span>02</span><div><h2>Hasil dan material</h2><p>Catat hasil akhir serta material yang benar-benar terpakai.</p></div></div>
        <div className="form-grid">
          <label><span>Status pekerjaan</span><select name="status" defaultValue="Selesai"><option>Selesai</option><option>Pending</option></select></label>
          <label><span>Nomor work order</span><input name="workOrder" placeholder="Contoh: WO-260720-019" /></label>
          <label className="full"><span>Material terpakai</span><input name="materials" placeholder="Contoh: Kabel drop 20 m, konektor 2 pcs" /></label>
          <label className="full"><span>Catatan teknisi</span><textarea name="notes" rows={4} placeholder="Kondisi lapangan, kendala, atau tindak lanjut..." /></label>
        </div>
        <div className="form-footer"><p>Data dapat dilihat kembali melalui menu Riwayat kerja.</p><button className="primary-action" type="submit">Simpan pekerjaan</button></div>
      </form>

      <aside className="form-aside">
        <article className="panel incentive-guide"><p className="eyebrow">SIMULASI INSENTIF</p><h2>Nilai per pekerjaan</h2><div><span>Pasang baru</span><strong>Rp20.000</strong></div><div><span>Assurance</span><strong>Rp15.000</strong></div><div><span>Maintenance</span><strong>Rp10.000</strong></div><small>Insentif dihitung setelah pekerjaan berstatus selesai dan diverifikasi pimpinan.</small></article>
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
          {["Semua", "Selesai", "Pending", "Pasang Baru", "Assurance", "Maintenance"].map((item) => <button key={item} className={filter === item ? "active" : ""} onClick={() => setFilter(item)}>{item}</button>)}
        </div>
      </div>
      <div className="history-summary"><span><b>{jobs.length}</b> pekerjaan ditampilkan</span><span><b>{jobs.filter((job) => job.status === "Selesai").length}</b> selesai</span><span><b>{rupiah(jobs.reduce((sum, job) => sum + job.incentive, 0))}</b> estimasi insentif</span></div>
      <JobTable jobs={jobs} showTechnician={role === "pimpinan"} />
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

function JobTable({ jobs, showTechnician = false }: { jobs: Job[]; showTechnician?: boolean }) {
  return (
    <div className="table-scroll"><table className="job-table"><thead><tr><th>Work order</th>{showTechnician && <th>Teknisi</th>}<th>Pelanggan / lokasi</th><th>Jenis</th><th>Status</th><th>Material</th><th>Insentif</th></tr></thead><tbody>{jobs.map((job) => <tr key={job.id}><td><strong>{job.id}</strong><small>{job.date}</small></td>{showTechnician && <td>{job.technician}</td>}<td><strong>{job.customer}</strong><small>{job.location}</small></td><td><span className={`type ${job.type.toLowerCase().replace(" ", "-")}`}>{job.type}</span></td><td><span className={`status ${job.status.toLowerCase()}`}><i />{job.status}</span></td><td>{job.materials}</td><td><strong>{job.incentive ? rupiah(job.incentive) : "-"}</strong></td></tr>)}</tbody></table>{jobs.length === 0 && <div className="empty-state"><strong>Belum ada pekerjaan</strong><span>Coba pilih filter lainnya.</span></div>}</div>
  );
}
