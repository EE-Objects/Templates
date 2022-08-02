<?php
namespace EeObjects\Templates;

use EeObjects\Exceptions\Templates\ParserException;
use CI_DB_mysqli_result;

class Parser
{
    /**
     * @var int
     */
    protected int $site_id = 1;

    /**
     * @param int $site_id
     */
    public function __construct(int $site_id)
    {
        $this->site_id = $site_id;
        if (!isset(ee()->TMPL2)) {
            ee()->load->library('template', null, 'TMPL2');
        }
    }

    /**
     * @return int
     */
    public function getSiteId(): int
    {
        return $this->site_id;
    }

    /**
     * @param string $template
     * @param array $vars
     * @param array $custom_vars
     * @return string
     * @throws ParserException
     */
    public function template(string $template, array $vars = [], array $custom_vars = []): string
    {
        $template_data = $this->getTemplate($template);
        ee()->TMPL2->parse_php = $template_data['allow_php'];
        ee()->TMPL2->php_parse_location = $template_data['php_parse_location'];
        ee()->TMPL2->template_type = ee()->functions->template_type = $template_data['template_type'];
        if ($vars) {
            $template_data['template_data'] = ee()->TMPL2->parse_variables($template_data['template_data'], [$vars]);
        }

        ee()->TMPL2->parse($template_data['template_data']);
        return ee()->TMPL2->parse_globals(ee()->TMPL2->final_template);;
    }

    /**
     * @param $str
     * @param array $vars
     * @return string
     */
    public function str($str, array $vars = [], array $custom_vars = []): string
    {
        return ee()->TMPL2->parse_variables($str, [$vars]);;
    }

    /**
     * @param string $template_path
     * @return array|null
     * @throws ParserException
     */
    protected function getTemplate(string $template_path): ?array
    {
        $template = explode('/', $template_path);
        $template_group = $template[0] ?? null;
        $template_name = $template[1] ?? 'index';
        if (is_null($template_group)) {
            throw new ParserException("Cannot find group from your template path: " . $template_path);
        }

        $query = ee()->db->select('template_data, template_type, allow_php, php_parse_location, template_id')
            ->join('template_groups', 'templates.group_id = template_groups.group_id')
            ->where('group_name', $template_group)
            ->where('template_name', $template_name)
            ->where('templates.site_id', $this->getSiteId())
            ->get('templates');

        if ($query instanceof CI_DB_mysqli_result) {
            $template_data = $query->row_array();
            if (PATH_TMPL && ee()->config->item('save_tmpl_files') === 'y') {
                $template_data['template_data'] = $this->getTemplateFileData($template_group, $template_name, $template_data['template_type']);
            }

            $template_data['template_data'] = str_replace(["\r\n", "\r"], "\n", $template_data['template_data']);

            ee()->TMPL2->group_name = $template_group;
            ee()->TMPL2->template_name = $template_name;
            ee()->TMPL2->template_id = $template_data['template_id'];
            ee()->TMPL2->template_type = $template_data['template_type'];

            return $template_data;
        }

        $this->logger()->debug('Email template not found ' . $template_path);
        return null;
    }

    /**
     * @param $template_group
     * @param $template_name
     * @param $template_type
     * @return string|null
     */
    protected function getTemplateFileData(string $template_group, string $template_name, string $template_type): ?string
    {
        ee()->load->library('api');
        ee()->legacy_api->instantiate('template_structure');
        $file = PATH_TMPL . ee()->config->item('site_short_name') . '/'
            . $template_group . '.group/' . $template_name
            . ee()->api_template_structure->file_extensions($template_type);

        if (file_exists($file)) {
            return file_get_contents($file);
        }

        $this->logger()->debug('Email template file not found ' . $template_group . '/' . $template_name);
        return null;
    }
}