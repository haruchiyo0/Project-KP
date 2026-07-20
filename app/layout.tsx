import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "IndiHome Field | Monitoring Pekerjaan Teknisi",
  description: "Aplikasi internal untuk pencatatan work order dan rekap pekerjaan teknisi IndiHome.",
  openGraph: {
    title: "IndiHome Field",
    description: "Monitoring Pekerjaan Teknisi",
    images: ["https://teknik-kerja-monitor.brownie-anode-2gdbf1.chatgpt.site/og.png"],
  },
  twitter: {
    card: "summary_large_image",
    title: "IndiHome Field",
    description: "Monitoring Pekerjaan Teknisi",
    images: ["https://teknik-kerja-monitor.brownie-anode-2gdbf1.chatgpt.site/og.png"],
  },
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="id">
      <body>{children}</body>
    </html>
  );
}
