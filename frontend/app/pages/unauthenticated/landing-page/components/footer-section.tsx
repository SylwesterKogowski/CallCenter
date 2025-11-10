import * as React from "react";

interface FooterLink {
  label: string;
  url: string;
}

interface CompanyInfo {
  name?: string;
  address?: string;
  phone?: string;
  email?: string;
  links?: FooterLink[];
}

interface FooterSectionProps {
  companyInfo?: CompanyInfo;
}

export const FooterSection: React.FC<FooterSectionProps> = ({ companyInfo }) => {
  const hasLinks = companyInfo?.links && companyInfo.links.length > 0;

  return (
    <footer
      className="border-t border-slate-200 bg-white/80 py-10 text-sm text-slate-600 backdrop-blur dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-300"
      role="contentinfo"
    >
      <div className="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
        <div className="space-y-2 text-center sm:text-left">
          {companyInfo?.name ? (
            <p className="text-base font-semibold text-slate-900 dark:text-slate-100">
              {companyInfo.name}
            </p>
          ) : null}
          {companyInfo?.address ? <p>{companyInfo.address}</p> : null}
          {companyInfo?.phone ? (
            <p>
              Telefon:{" "}
              <a
                href={`tel:${companyInfo.phone.replace(/\s+/g, "")}`}
                className="text-blue-600 hover:underline dark:text-blue-400"
              >
                {companyInfo.phone}
              </a>
            </p>
          ) : null}
          {companyInfo?.email ? (
            <p>
              Email:{" "}
              <a
                href={`mailto:${companyInfo.email}`}
                className="text-blue-600 hover:underline dark:text-blue-400"
              >
                {companyInfo.email}
              </a>
            </p>
          ) : null}
        </div>

        {hasLinks ? (
          <ul className="flex flex-wrap justify-center gap-4 text-center sm:justify-end">
            {companyInfo?.links?.map((link) => (
              <li key={link.label}>
                <a
                  href={link.url}
                  className="text-blue-600 hover:underline dark:text-blue-400"
                >
                  {link.label}
                </a>
              </li>
            ))}
          </ul>
        ) : null}
      </div>
    </footer>
  );
};

