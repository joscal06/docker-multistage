// Endpoint de salud que usa el HEALTHCHECK del contenedor (y docker compose
// para ordenar el arranque). No consulta la API: solo confirma que el
// servidor de Next.js responde.
export const dynamic = "force-dynamic";

export function GET() {
  return Response.json({
    status: "ok",
    servicio: "frontend",
    node: process.version,
    uptime_s: Math.round(process.uptime()),
  });
}
