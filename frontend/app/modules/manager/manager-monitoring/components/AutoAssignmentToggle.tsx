import * as React from "react";

export interface AutoAssignmentToggleProps {
  enabled: boolean;
  onToggle: (enabled: boolean) => void;
  isLoading?: boolean;
}

export const AutoAssignmentToggle: React.FC<AutoAssignmentToggleProps> = ({
  enabled,
  onToggle,
  isLoading = false,
}) => {
  return (
    <button
      type="button"
      className={`flex items-center rounded-full border px-4 py-2 transition ${
        enabled ? "border-green-600 bg-green-50 text-green-700" : "border-gray-300 bg-white text-gray-700"
      } ${isLoading ? "opacity-50" : "hover:shadow"}`}
      aria-pressed={enabled}
      onClick={() => {
        if (!isLoading) {
          onToggle(!enabled);
        }
      }}
      disabled={isLoading}
    >
      <span
        className={`mr-3 inline-block h-5 w-10 rounded-full border transition ${
          enabled ? "border-green-500 bg-green-500" : "border-gray-300 bg-gray-200"
        }`}
      >
        <span
          className={`relative left-0 top-0 inline-block h-5 w-5 rounded-full bg-white shadow transition ${
            enabled ? "translate-x-5" : ""
          }`}
        />
      </span>
      <span className="text-sm font-medium">
        {enabled ? "Automatyczne przypisywanie włączone" : "Automatyczne przypisywanie wyłączone"}
      </span>
    </button>
  );
};


