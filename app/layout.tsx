import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "TeknikKerja | Monitoring Pekerjaan Teknisi",
  description: "Aplikasi internal untuk pencatatan pekerjaan, material, dan rekap insentif teknisi.",
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="id">
      <body>{children}</body>
    </html>
  );
}
